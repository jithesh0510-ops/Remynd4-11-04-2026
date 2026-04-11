<?php

namespace Drupal\crs_sync\Sync;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Drupal\user\Entity\User;
use Drupal\Component\Utility\Random;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\node\Entity\Node;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;





/**
 * Idempotent sync manager for legacy → Drupal users + profiles.
 */
class SyncManager {

  protected EntityTypeManagerInterface $etm;
  protected Connection $db;
  protected Connection $legacy;
  protected LoggerInterface $logger;
  protected TimeInterface $time;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $db,
    LoggerInterface $logger,
    TimeInterface $time
  ) {
    $this->etm = $entity_type_manager;
    $this->db = $db;
    $this->logger = $logger;
    $this->time = $time;
    // Secondary connection must be defined in settings.php as key 'legacy'.
    $this->legacy = Database::getConnection('default', 'legacy');
  }

  /* ===================== PUBLIC ENTRYPOINTS ===================== */

  public function syncCompanies(): int {
    $count = 0;
    $result = $this->legacy->select('qs_company_master', 'c')->fields('c')->execute();
    foreach ($result as $row) {
      $legacy_id = (int) ($row->id ?? $row->company_id ?? 0);
      if ($legacy_id <= 0) { continue; }

      $mail = $this->firstNotEmpty($row, ['email', 'mail', 'company_email']) ?: "company{$legacy_id}@example.invalid";
      $name = $this->firstNotEmpty($row, ['name', 'company_name', 'full_name']) ?: "Company {$legacy_id}";
      $name = $this->utf($name); // UTF

      $data = $this->buildUserDataFromRow($row); // UTF inside
      $uid  = $this->ensureUser('company', $legacy_id, $mail, $name, $data);

      // Upsert a "company" profile with optional full name.
      $this->upsertProfile($uid, 'company', $this->mapUtf([
        'field_company_id' => $row->company_code,
        'field_company_name' => $row->company_name,
        'field_no_of_coach' => $row->no_of_coach,
        'field_no_of_employees' => $row->no_of_employees,
        'field_no_of_password_generated' => $row->no_of_password_generated,
      ]));
      $count++;
    }
    return $count;
  }

  public function syncCoaches(): int {
    $count = 0;
    $query = $this->legacy->select('qs_coach_master', 'c');
    $query->fields('c'); // includes c.coach_id (or c.id in some schemas)
    $query->leftJoin('qs_company_coach_details', 'ccd', 'ccd.coach_id = c.coach_id');
    $query->addField('ccd', 'company_id', 'company_id');
    $result = $query->execute();

    // Group legacy rows by coach_id and collect company_ids.
    $by_coach = [];
    foreach ($result as $row) {
      $legacy_id = (int) ($row->coach_id ?? $row->id ?? 0);
      if ($legacy_id <= 0) {
        continue;
      }
      if (!isset($by_coach[$legacy_id])) {
        $by_coach[$legacy_id] = [
          'row' => $row,                 // first row carries the coach data
          'company_legacy_ids' => [],    // we’ll collect mapped company IDs
        ];
      }
      if (!empty($row->company_id)) {
        $by_coach[$legacy_id]['company_legacy_ids'][] = (int) $row->company_id;
      }
    }

    foreach ($by_coach as $legacy_id => $bundle) {
      $row = $bundle['row'];

      $mail = $this->firstNotEmpty($row, ['email', 'mail']) ?: "coach{$legacy_id}@example.invalid";
      $name = $this->firstNotEmpty($row, ['name', 'full_name', 'first_name']) ?: "Coach {$legacy_id}";
      $name = $this->utf($name); // UTF

      $data = $this->buildUserDataFromRow($row); // UTF inside
      $uid  = $this->ensureUser('coach', $legacy_id, $mail, $name, $data);

      // Map legacy company IDs -> Drupal UIDs via the mapping table.
      $company_uids = [];
      $legacy_company_ids = array_values(array_unique($bundle['company_legacy_ids']));
      foreach ($legacy_company_ids as $cid_legacy) {
        if (!$cid_legacy) { continue; }
        if ($mapped = $this->lookupUid('company', (int) $cid_legacy)) {
          $company_uids[] = $mapped;
        }
      }

      // Update coach profile.
      $this->upsertProfile($uid, 'coach', $this->mapUtf([
        'field_company' => $company_uids,
        'field_enable_the_coach_will_see' => $row->see_actionreport_result,
        'field_see_laggards_to_stars' => $row->lagard_to_stars,
        'field_see_previous_date' => $row->see_previous_date,
        'field_see_questionnaire_result' => $row->see_questionnaire_result,
        'field_see_skills_assessment' => $row->skills_assessment,
      ]));

      $count++;
    }
    return $count;
  }

  public function syncEmployees(): int {
    $count = 0;

    // JOIN qs_branch_master + qs_job_position and fetch their names too.
    $query = $this->legacy->select('qs_employee_master', 'e')
      ->fields('e');
    $query->leftJoin('qs_branch_master', 'b', 'b.branch_id = e.branch_id');
    $query->leftJoin('qs_job_position', 'j', 'j.job_position_id = e.job_position_id');
    $query->addField('b', 'branch_name', 'branch_name');
    $query->addField('j', 'job_position_name', 'job_position_name');
    $query->orderBy('e.assigned_coachs_id', 'ASC'); // keep your legacy ordering

    $result = $query->execute();

    foreach ($result as $row) {
      $legacy_id = (int) ($row->id ?? $row->employee_id ?? 0);
      if ($legacy_id <= 0) { continue; }

      $mail = $this->firstNotEmpty($row, ['email', 'mail']) ?: "employee{$legacy_id}@example.invalid";
      $name = $this->firstNotEmpty($row, ['name', 'full_name', 'first_name']) ?: "Employee {$legacy_id}";
      $name = $this->utf($name); // UTF

      // Build common user data (names, phone, address, website, etc.).
      $data = $this->buildUserDataFromRow($row); // UTF inside

      // Also carry over branch & job position labels from the JOIN.
      $data['branch_name']       = isset($row->branch_name) ? (string) $row->branch_name : '';
      $data['job_position_name'] = isset($row->job_position_name) ? (string) $row->job_position_name : '';

      $uid = $this->ensureUser('employee', $legacy_id, $mail, $name, $data);

      // Prepare profile fields (only set if the field exists on the bundle).
      $company_uid = 0;
      if (!empty($row->company_id)) {
        $company_uid = $this->lookupUid('company', (int) $row->company_id);
      }
      $coach_uids = [];
      foreach ((array) $this->splitIds($row->assigned_coachs_id ?? '') as $cid) {
        if ($m = $this->lookupUid('coach', (int) $cid)) {
          $coach_uids[] = $m;
        }
      }

      $this->upsertProfile($uid, 'employee', $this->mapUtf([
        'field_company' => $company_uid,
        'field_coach' => $coach_uids,
        'field_employee_number' => $row->employee_id,
        'field_job_position' => $row->job_position_name,
        'field_branch' => $row->branch_name,
        'field_view_report' => $row->view_report,
      ]));

      $count++;
    }
    return $count;
  }

  /* ===================== CORE ENSURE/UPSERT (IDEMPOTENT) ===================== */

  /**
   * Create/update a Drupal user and custom fields, then upsert the legacy map.
   *
   * - If a map exists → update that user.
   * - Else, if a user with same email exists → use it and (up)sert map.
   * - Else create a new user.
   *
   * @return int UID
   */
  protected function ensureUser(string $type, int $legacy_id, string $mail, string $name, array $data = []): int {
    $role_by_type = ['company' => 'company', 'coach' => 'coach', 'employee' => 'employee'];
    if (!isset($role_by_type[$type])) {
      throw new \InvalidArgumentException("Unknown type '$type'");
    }
    $role = $role_by_type[$type];

    $storage = $this->etm->getStorage('user');

    // 1) Try map.
    $uid = $this->lookupUid($type, $legacy_id);
    $account = $uid ? $storage->load($uid) : NULL;

    // 2) Fallback by email (and later upsert the map to this uid).
    if (!$account && $mail) {
      $uids = $storage->getQuery()->condition('mail', $mail)->accessCheck(FALSE)->range(0, 1)->execute();
      if ($uids) { $account = $storage->load(reset($uids)); }
    }

    $is_new = FALSE;
    if (!$account) {
      $account = User::create([
        'name'   => $this->uniqueUsername($data['full_name'] ?? $name, $mail),
        'mail'   => $mail,
        'status' => 1,
      ]);
      $account->enforceIsNew();
      $account->setPassword($this->generatePassword());
      $is_new = TRUE;
    }

    // Role ensure.
    if (!$account->hasRole($role)) {
      $account->addRole($role);
    }

    // Optionally refresh username when we auto-generated it before and we have a better full name now.
    if (!$is_new && !empty($data['full_name'])) {
      $current_name = (string) $account->getAccountName();
      // Heuristic: if current name looks like our previous fallback, allow rename.
      if (preg_match('/^(company|coach|employee|user)[_\-]?\d+$/', $current_name)) {
        $new_name = $this->uniqueUsername($data['full_name'], $account->getEmail() ?? $mail);
        if ($new_name !== $current_name) {
          $account->setUsername($new_name);
        }
      }
    }

    // Email update (if changed & not in use by someone else).
    if ($mail && $mail !== ($account->getEmail() ?? '')) {
      $exists = (bool) $storage->getQuery()->condition('mail', $mail)->accessCheck(FALSE)->execute();
      if (!$exists) {
        $account->setEmail($mail);
      }
      else {
        $this->logger->warning('Skipped email update for uid @u to @m (already in use).', ['@u' => $account->id(), '@m' => $mail]);
      }
    }

    // === Custom user fields: only set when a non-empty value is provided ===
    $this->setIfHas($account, 'field_first_name',  $data['first_name']  ?? NULL);
    $this->setIfHas($account, 'field_middle_name', $data['middle_name'] ?? NULL);
    $this->setIfHas($account, 'field_last_name',   $data['last_name']   ?? NULL);

    $full_name = $data['full_name'] ?? trim(implode(' ', array_filter([
      $data['first_name'] ?? NULL,
      $data['middle_name'] ?? NULL,
      $data['last_name'] ?? NULL,
    ]))) ?: $name;
    $this->setIfHas($account, 'field_full_name', $full_name);

    $this->setIfHas($account, 'field_phone_no', $data['phone_no'] ?? NULL);

    if ($account->hasField('field_is_delete') && array_key_exists('is_delete', $data)) {
      $account->set('field_is_delete', (int) (!empty($data['is_delete'])));
    }

    // Address (skip if everything empty).
    if ($account->hasField('field_address') && !empty($data['address']) && is_array($data['address'])) {
      $addr = $this->buildAddress($data['address']);
      if ($addr !== NULL) {
        $account->set('field_address', $addr);
      }
    }

    // Website link.
    if ($account->hasField('field_website') && !empty($data['website'])) {
      if ($link = $this->prepareLink($data['website'])) {
        $account->set('field_website', $link);
      }
    }

    // Feeds item.
    if ($account->hasField('feeds_item') && !empty($data['feeds_item_id'])) {
      $account->set('feeds_item', ['target_id' => (int) $data['feeds_item_id']]);
    }

    // User picture (always refresh if URL provided—legacy may have changed).
    if ($account->hasField('user_picture') && !empty($data['user_picture_url'])) {
      if ($fid = $this->ensureFileFromUrl($data['user_picture_url'], 'public://user-pictures')) {
        $account->set('user_picture', ['target_id' => $fid]);
      }
    }

    $account->save();
    $uid = (int) $account->id();

    // Upsert the legacy map to point to this uid now.
    $this->upsertMap($type, $legacy_id, $uid);

    return $uid;
  }

  /**
   * Create or update a Profile entity for a user (if Profile module is enabled).
   */
  protected function upsertProfile(int $uid, string $bundle, array $fields): void {
    if (!\Drupal::moduleHandler()->moduleExists('profile')) { return; }
    /** @var \Drupal\profile\ProfileStorageInterface $storage */
    $storage = $this->etm->getStorage('profile');

    $pids = $storage->getQuery()
      ->condition('type', $bundle)
      ->condition('uid', $uid)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    $profile = $pids ? $storage->load(reset($pids)) : NULL;
    if (!$profile) {
      $profile = \Drupal\profile\Entity\Profile::create(['type' => $bundle, 'uid' => $uid, 'status' => 1]);
    }
    foreach ($fields as $name => $value) {
      // Normalize strings (and nested arrays) before setting.
      $value = $this->mapUtf($value);
      if ($profile->hasField($name) && $value !== NULL && $value !== '') {
        $profile->set($name, $value);
      }
    }
    $profile->save();
  }

  /* ===================== HELPERS ===================== */

  /** Map upsert (idempotent). */
  protected function upsertMap(string $type, int $legacy_id, int $uid): void {
    // Prefer MERGE (upsert).
    $this->db->merge('crs_sync_legacy_map')
      ->key(['type' => $type, 'legacy_id' => $legacy_id])
      ->fields([
        'uid'     => $uid,
        'created' => $this->time->getRequestTime(), // harmless to refresh
      ])
      ->execute();
  }

  /** Pull value by candidate column names. */
  protected function firstNotEmpty(object $row, array $candidates): string {
    foreach ($candidates as $c) {
      if (isset($row->{$c}) && trim((string) $row->{$c}) !== '') {
        return $this->utf((string) $row->{$c});
      }
    }
    return '';
  }

  /** Split IDs from comma/space-separated input into int array. */
  protected function splitIds($raw): array {
    if (!is_string($raw)) { return []; }
    $parts = preg_split('/[,\s]+/', trim($raw)) ?: [];
    return array_values(array_filter(array_map('intval', $parts)));
  }

  /** Lookup Drupal UID from legacy map table. */
  protected function lookupUid(string $type, int $legacy_id): int {
    return (int) $this->db->select('crs_sync_legacy_map', 'm')
      ->fields('m', ['uid'])
      ->condition('type', $type)
      ->condition('legacy_id', $legacy_id)
      ->execute()
      ->fetchField();
  }

  /** Generate a random password via service or fallback. */
  protected function generatePassword(int $len = 16): string {
    if (\Drupal::hasService('password_generator')) {
      return \Drupal::service('password_generator')->generate($len);
    }
    return (new Random())->string($len);
  }

  /** Ensure unique username. */
  protected function uniqueUsername(string $preferred, string $mail): string {
    $preferred = $this->utf($preferred);
    $base = trim($preferred) !== '' ? $preferred : (explode('@', $mail)[0] ?? 'user');
    $base = preg_replace('/[^a-z0-9_.-]+/i', '_', strtolower($base));
    $name = $base;
    $i = 0;
    $storage = $this->etm->getStorage('user');
    do {
      $exists = (bool) $storage->getQuery()->condition('name', $name)->accessCheck(FALSE)->execute();
      if (!$exists) { break; }
      $i++; $name = $base . '_' . $i;
    } while ($i < 9999);
    return $name;
  }

  /** Convert a raw URL to a Link field item (array). */
  protected function prepareLink(string $raw): ?array {
    $raw = trim($this->utf($raw));
    if ($raw === '') { return NULL; }
    if (!preg_match('#^https?://#i', $raw)) {
      $raw = 'https://' . $raw;
    }
    if (!UrlHelper::isValid($raw, TRUE)) { return NULL; }
    return ['uri' => $raw, 'title' => NULL, 'options' => []];
  }

  /**
   * Build a proper Address field item from mixed legacy input.
   * Returns NULL if all parts are empty (so we don't overwrite existing data).
   */
  protected function buildAddress(array $in): ?array {
    $country = $this->firstNonEmpty($in, ['country_code', 'country', 'country_name']);
    $admin   = $this->firstNonEmpty($in, ['administrative_area', 'state', 'region', 'province']);
    $city    = $this->firstNonEmpty($in, ['locality', 'city', 'town']);
    $postal  = $this->firstNonEmpty($in, ['postal_code', 'postcode', 'zip', 'zipcode']);
    $line1   = $this->firstNonEmpty($in, ['address_line1', 'address1', 'street1', 'street', 'address']);
    $line2   = $this->firstNonEmpty($in, ['address_line2', 'address2', 'street2']);
    $org     = $this->firstNonEmpty($in, ['organization', 'company', 'org']);
    $given   = $this->firstNonEmpty($in, ['given_name', 'first_name']);
    $family  = $this->firstNonEmpty($in, ['family_name', 'last_name']);

    if (!$line1 && !empty($in['address']) && is_string($in['address'])) {
      $lines = preg_split('/\R+/', trim($in['address']));
      $line1 = $this->utf($lines[0] ?? '');
      $line2 = $this->utf($lines[1] ?? '');
    }

    // All empty? return NULL to skip.
    $all = [$country,$admin,$city,$postal,$line1,$line2,$org,$given,$family];
    if (!array_filter($all, fn($v) => (string) $v !== '')) {
      return NULL;
    }

    // Normalize country to ISO2 if needed.
    if ($country && strlen($country) !== 2 && \Drupal::hasService('address.country_repository')) {
      $repo = \Drupal::service('address.country_repository');
      foreach ($repo->getList() as $code => $label) {
        if (strcasecmp($label, $country) === 0) { $country = $code; break; }
      }
    }

    return [
      'country_code'        => $country ? strtoupper($country) : NULL,
      'administrative_area' => $admin ?: NULL,
      'locality'            => $city ?: NULL,
      'dependent_locality'  => NULL,
      'postal_code'         => $postal ?: NULL,
      'sorting_code'        => NULL,
      'address_line1'       => $line1 ?: NULL,
      'address_line2'       => $line2 ?: NULL,
      'organization'        => $org ?: NULL,
      'given_name'          => $given ?: NULL,
      'family_name'         => $family ?: NULL,
      'langcode'            => NULL,
    ];
  }

  /** Pick first non-empty string from an array by candidate keys. */
  protected function firstNonEmpty(array $arr, array $keys): string {
    foreach ($keys as $k) {
      if (isset($arr[$k]) && trim((string) $arr[$k]) !== '') {
        return $this->utf((string) $arr[$k]);
      }
    }
    return '';
  }

  /**
   * Download a file into public:// and return fid.
   */
  protected function ensureFileFromUrl(string $url, string $directory = 'public://user-pictures'): ?int {
    try {
      $url = trim($this->utf($url)); // UTF
      if ($url === '' || !UrlHelper::isValid($url, TRUE)) { return NULL; }

      $http = \Drupal::httpClient();
      $res = $http->request('GET', $url, ['timeout' => 20]);
      if ($res->getStatusCode() !== 200) { return NULL; }

      $fs = \Drupal::service('file_system');
      $fs->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

      $basename = basename(parse_url($url, PHP_URL_PATH) ?: ('image-' . uniqid() . '.jpg'));
      $dest = $directory . '/' . $basename;

      $data = (string) $res->getBody();
      $dest = $fs->saveData($data, $dest, FileSystemInterface::EXISTS_RENAME);

      $file = File::create(['uri' => $dest, 'status' => File::STATUS_PERMANENT]);
      $file->save();
      return (int) $file->id();
    }
    catch (\Throwable $e) {
      $this->logger->warning('Picture fetch failed @u: @m', ['@u' => $url, '@m' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Set a field value if the entity actually has that field (ignores empty).
   */
  protected function setIfHas(FieldableEntityInterface $entity, string $field, $value, bool $allow_empty = false): void {
    if (!$entity->hasField($field)) { return; }
    if ($value === NULL) { return; }
    if (!$allow_empty && is_string($value) && trim($value) === '') { return; }
    // Normalize strings or arrays of strings before setting.
    $entity->set($field, $this->mapUtf($value));
  }

  /**
   * Build normalized $data from a legacy row (names, phone, website, address, etc.)
   */
  protected function buildUserDataFromRow(object $row): array {
    // Names & phone.
    $first  = $this->firstNotEmpty($row, ['first_name', 'firstname', 'fname']);
    $middle = $this->firstNotEmpty($row, ['middle_name', 'middlename', 'mname']);
    $last   = $this->firstNotEmpty($row, ['last_name', 'lastname', 'lname']);
    $full   = $this->firstNotEmpty($row, ['full_name', 'name', 'display_name']);
    $phone  = $this->firstNotEmpty($row, ['phone', 'phone_no', 'mobile', 'contact']);

    // Website & picture.
    $website = $this->firstNotEmpty($row, ['website', 'url', 'homepage']);
    $avatar  = $this->firstNotEmpty($row, ['avatar', 'photo', 'picture', 'image_url']);

    // Feeds item id (optional).
    $feeds_item_id = (int) ($row->feeds_item_id ?? 0);

    // Soft-delete flag (optional).
    $is_delete = (int) ($row->is_delete ?? $row->deleted ?? 0);

    // Address candidates (the buildAddress() helper will normalize these).
    $address = [
      'country'             => $this->firstNotEmpty($row, ['country', 'country_code', 'country_name']),
      'administrative_area' => $this->firstNotEmpty($row, ['state', 'region', 'province', 'administrative_area']),
      'locality'            => $this->firstNotEmpty($row, ['city', 'locality', 'town']),
      'postal_code'         => $this->firstNotEmpty($row, ['postal', 'postcode', 'zip', 'zipcode']),
      'address_line1'       => $this->firstNotEmpty($row, ['address1', 'address_line1', 'street', 'street1']),
      'address_line2'       => $this->firstNotEmpty($row, ['address2', 'address_line2', 'street2']),
      'organization'        => $this->firstNotEmpty($row, ['organization', 'company', 'org']),
      'given_name'          => $first,
      'family_name'         => $last,
    ];

    // If we only have a single multi-line 'address' blob, pass it through.
    if (empty($address['address_line1']) && !empty($row->address) && is_string($row->address)) {
      $address['address'] = $this->utf((string) $row->address); // UTF blob
    }

    return $this->mapUtf([
      'first_name'       => $first,
      'middle_name'      => $middle,
      'last_name'        => $last,
      'full_name'        => $full,
      'phone_no'         => $phone,
      'is_delete'        => $is_delete,
      'website'          => $website,
      'user_picture_url' => $avatar,
      'feeds_item_id'    => $feeds_item_id ?: NULL,
      'address'          => $address,
    ]);
  }

  /* ===================== UTF HELPERS (ADDED) ===================== */

  /**
   * Normalize any scalar/array (recursively) so all strings are UTF-8 & NFC.
   * - Fixes common mojibake like "Ø¹Ø±Ø¨ÙŠ" → "عربي"
   * - Accepts scalars or nested arrays (e.g., field payloads)
   */
  protected function mapUtf($value) {
    if (is_string($value)) {
      return $this->utf($value);
    }
    if (is_array($value)) {
      $out = [];
      foreach ($value as $k => $v) {
        $out[$k] = $this->mapUtf($v);
      }
      return $out;
    }
    return $value;
  }

	/**
	 * Force text to clean UTF-8: strip invalid bytes then normalize.
	 */
	private function utf(?string $s): string {
	  $s = trim((string) $s);
	  if ($s === '') return '';

	  // 1) Strip invalid UTF-8 bytes (replacement for deprecated Unicode::convertToUtf8).
	  $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
	  if ($clean !== false) {
		$s = $clean;
	  }

	  // 2) If it still looks like classic mojibake (Ø, Ù, Ã, Â), repair by
	  // reversing the “UTF-8 shown as Latin-1” path using utf8_decode().
	  if (preg_match('/[ØÙÃÂ]/u', $s) && !preg_match('/\p{Arabic}/u', $s)) {
		$bytes = @utf8_decode($s); // returns ISO-8859-1 byte string
		if ($bytes !== false && mb_detect_encoding($bytes, 'UTF-8', true)) {
		  $s = $bytes;
		}
	  }

	  // 3) Normalize to NFC for consistent storage/rendering.
	  if (class_exists(\Normalizer::class)) {
		$n = \Normalizer::normalize($s, \Normalizer::FORM_C);
		if ($n !== false) { $s = $n; }
	  }

	  return $s;
	}


  /* ===================== QUESTIONNAIRE SYNC ===================== */

	/**
	 * Sync Questionnaires from legacy DB → Drupal Node + Paragraphs.
	 */
	public function syncQuestionnaires(): array {
	  $created = 0; $updated = 0;

	  $result = $this->legacy->select('qs_questionnaire_master', 'q')
		->fields('q')
		->condition('q.status', 1)
		->execute();

	  foreach ($result as $row) {
		  
		/*echo mb_convert_encoding($row->questionnaire_name, "UTF-8", mb_detect_encoding($row->questionnaire_name)); 
		echo $this->utf($row->questionnaire_name);  
		echo "<br>";*/	
		  
		$qid_legacy = (int) ($row->questionnaire_id ?? 0);
		if ($qid_legacy <= 0) { continue; }

		$rawTitle = trim((string) ($row->questionnaire_name ?? ''));
		$title = $this->utf($rawTitle ?: "Questionnaire #$qid_legacy");

		// 1) Ensure the Questionnaire node (lookup by UTF title).
		[$node, $was_created] = $this->ensureQuestionnaireNode($qid_legacy, $title);

		// 2) Create Questionnaire paragraph.
		$q_para = \Drupal\paragraphs\Entity\Paragraph::create([
		  'type' => 'questionnaire',
		  'field_title' => $this->utf($title),
		]);

		// 3) Attach Scores (unique options).
		$this->attachScores($qid_legacy, $q_para);

		// 4) Attach Categories (+ Questions + Sub/Sub-sub).
		$this->attachCategories($qid_legacy, $q_para);

		// Save paragraph and attach to node.
		$q_para->save();
		$node->set('field_create_questionnaire', [
		  [
			'target_id' => $q_para->id(),
			'target_revision_id' => $q_para->getRevisionId(),
		  ],
		]);

		// Keep title normalized.
		$node->setTitle($this->utf($node->getTitle()));
		$node->save();

		if ($was_created) { $created++; } else { $updated++; }
		$this->logger->info('Questionnaire @nid synced', ['@nid' => $node->id()]);
	  }

	  return [$created, $updated];
	}

	/**
	 * Ensure Questionnaire Node exists (lookup by UTF-normalized title).
	 */
	protected function ensureQuestionnaireNode(int $legacy_id, string $title): array {
	  $utfTitle = $this->utf($title);

	  $existing = $this->etm->getStorage('node')->loadByProperties([
		'type'  => 'questionnaire',
		'title' => $utfTitle,
	  ]);

	  if ($existing) {
		$node = reset($existing);
		if ($node->getTitle() !== $utfTitle) {
		  $node->setTitle($utfTitle);
		  $node->save();
		}
		return [$node, FALSE];
	  }

	  $node = \Drupal\node\Entity\Node::create([
		'type'   => 'questionnaire',
		'title'  => $utfTitle,
		'status' => 1,
	  ]);
	  $node->save();
	  return [$node, TRUE];
	}

	/**
	 * Attach Score options (unique answer_text_value) to Questionnaire paragraph.
	 */
	protected function attachScores(int $qid_legacy, \Drupal\paragraphs\Entity\Paragraph $questionnaire_paragraph): void {
	  // Build the select step by step; never reassign $query to addField()'s return value.
	  $query = $this->legacy->select('qs_answer_master', 'a');
	  $query->fields('a', ['answer_text', 'answer_text_value']);

	  $query->leftJoin('qs_question_answer_details', 'd', 'd.answer_text_id = a.answer_text_id');
	  $query->leftJoin('qs_question_master', 'q', 'q.question_id = d.question_id');

	  // IMPORTANT: addField() returns a string (field alias). Do NOT assign back to $query.
	  $qid_field = $query->addField('q', 'questionnaire_id'); // <- alias string (unused, but fine)

	  $query->condition('q.questionnaire_id', $qid_legacy);
	  $query->isNotNull('a.answer_text_value');

	  // Avoid duplicates (group by value + title within questionnaire).
	  $query->groupBy('a.answer_text_value');
	  $query->groupBy('a.answer_text');
	  $query->groupBy('q.questionnaire_id');

	  $query->orderBy('a.answer_text', 'ASC');

	  $results = $query->execute();

	  $score_paragraphs = [];
	  foreach ($results as $row) {
		$val = (string) $row->answer_text_value;
		if ($val === '') { continue; }

		$score = \Drupal\paragraphs\Entity\Paragraph::create([
		  'type' => 'options',
		  'field_option_title' => $this->utf($row->answer_text ?? ''),
		  'field_option_value' => $this->utf($val),
		]);
		$score->save();

		$score_paragraphs[] = [
		  'target_id' => $score->id(),
		  'target_revision_id' => $score->getRevisionId(),
		];
	  }

	  if ($score_paragraphs) {
		$questionnaire_paragraph->set('field_options', $score_paragraphs);
		$questionnaire_paragraph->save();
	  }
	}

	/**
	 * Attach Categories, Subcategories, Sub-subcategories, and Questions.
	 * Strict scoping to prevent duplicates:
	 * - Category: subcategory_id=0 AND subsubcategory_id=0
	 * - Subcategory: subcategory_id=X AND subsubcategory_id=0
	 * - Sub-subcategory: subcategory_id=X AND subsubcategory_id=Y
	 */
	protected function attachCategories(int $qid_legacy, \Drupal\paragraphs\Entity\Paragraph $q_para): void {
	  $categories = $this->legacy->select('qs_category_master', 'c')
		->fields('c')
		->condition('c.questionnaire_id', $qid_legacy)
		->condition('c.status', 1)
		->condition('c.is_delete', 0)
		->execute();

	  $cat_paras = [];
	  foreach ($categories as $cat) {
		$cat_para = \Drupal\paragraphs\Entity\Paragraph::create([
		  'type'         => 'category',
		  'field_title'  => $this->utf($cat->category_name ?? ''),
		  'field_weight' => (int) ($cat->priority ?? 0),
		]);

		// Category-level questions.
		$cat_questions = $this->createQuestionParasStrict(
		  $qid_legacy, (int) $cat->category_id, 0, 0, 'category'
		);
		if ($cat_questions) {
		  $cat_para->set('field_question', $cat_questions);
		}

		// Subcategories.
		$subcat_paras = [];
		$subcats = $this->legacy->select('qs_subcategory_master', 's')
		  ->fields('s')
		  ->condition('s.category_id', (int) $cat->category_id)
		  ->condition('s.questionnaire_id', $qid_legacy)
		  ->condition('s.status', 1)
		  ->condition('s.is_delete', 0)
		  ->execute();

		foreach ($subcats as $sub) {
		  $sub_para = \Drupal\paragraphs\Entity\Paragraph::create([
			'type'         => 'sub_category',
			'field_title'  => $this->utf($sub->subcategory_name ?? ''),
			'field_weight' => (int) ($sub->priority ?? 0),
		  ]);

		  // Subcategory-level questions.
		  $sub_questions = $this->createQuestionParasStrict(
			$qid_legacy, (int) $cat->category_id, (int) $sub->subcategory_id, 0, 'subcategory'
		  );
		  if ($sub_questions) {
			$sub_para->set('field_question', $sub_questions);
		  }

		  // Sub-subcategories.
		  $subsub_refs = [];
		  $subsubs = $this->legacy->select('qs_subsubcategory_master', 'ss')
			->fields('ss')
			->condition('ss.category_id', (int) $cat->category_id)
			->condition('ss.subcategory_id', (int) $sub->subcategory_id)
			->condition('ss.questionnaire_id', $qid_legacy)
			->condition('ss.status', 1)
			->condition('ss.is_delete', 0)
			->execute();

		  foreach ($subsubs as $ss) {
			$ss_para = \Drupal\paragraphs\Entity\Paragraph::create([
			  'type'         => 'sub_sub_category',
			  'field_title'  => $this->utf($ss->subsubcategory_name ?? ''),
			  'field_weight' => (int) ($ss->priority ?? 0),
			]);

			// Sub-subcategory-level questions.
			$ss_questions = $this->createQuestionParasStrict(
			  $qid_legacy, (int) $cat->category_id, (int) $sub->subcategory_id, (int) $ss->subsubcategory_id, 'subsub'
			);
			if ($ss_questions) {
			  $ss_para->set('field_question', $ss_questions);
			}

			$ss_para->save();
			$subsub_refs[] = $this->ref($ss_para);
		  }

		  if ($subsub_refs) {
			$sub_para->set('field_sub_sub_category', $subsub_refs);
		  }

		  $sub_para->save();
		  $subcat_paras[] = $this->ref($sub_para);
		}

		if ($subcat_paras) {
		  $cat_para->set('field_sub_category', $subcat_paras);
		}

		$cat_para->save();
		$cat_paras[] = $this->ref($cat_para);
	  }

	  if ($cat_paras) {
		$q_para->set('field_category', $cat_paras);
		$q_para->save();
	  }
	}

	/**
	 * Create question paragraphs with strict scoping (no duplicates).
	 * $level: 'category' | 'subcategory' | 'subsub'
	 */
	private function createQuestionParasStrict(
	  int $questionnaire_id,
	  int $category_id,
	  int $subcategory_id = 0,
	  int $subsubcategory_id = 0,
	  string $level = 'category'
	): array {
	  $q = $this->legacy->select('qs_question_master', 'q')
		->distinct()
		->fields('q')
		->condition('q.questionnaire_id', $questionnaire_id)
		->condition('q.category_id', $category_id)
		->condition('q.status', 1)
		->condition('q.is_delete', 0);

	  // Enforce exact scoping.
	  switch ($level) {
		case 'category':
		  $q->condition('q.subcategory_id', 0);
		  $q->condition('q.subsubcategory_id', 0);
		  break;
		case 'subcategory':
		  $q->condition('q.subcategory_id', $subcategory_id);
		  $q->condition('q.subsubcategory_id', 0);
		  break;
		case 'subsub':
		  $q->condition('q.subcategory_id', $subcategory_id);
		  $q->condition('q.subsubcategory_id', $subsubcategory_id);
		  break;
	  }

	 /* $q->orderBy('q.priority', 'ASC');*/
	  $result = $q->execute();

	  $refs = [];
	  foreach ($result as $row) {
		$p = \Drupal\paragraphs\Entity\Paragraph::create([
		  'type'        => 'question',
		  'field_title' => $this->utf($row->question_title ?? ''),
		  'field_hint'  => $this->utf($row->question_hint ?? ''),
		]);
		$p->save();
		$refs[] = $this->ref($p);
	  }
	  return $refs;
	}

	/** Make a reference array for a saved paragraph. */
	private function ref(\Drupal\paragraphs\Entity\Paragraph $p): array {
	  return [
		'target_id' => $p->id(),
		'target_revision_id' => $p->getRevisionId(),
	  ];
	}

  
  

	/**
	 * Map company↔questionnaire assignments (multiple) from legacy table
	 * qs_company_questionnaire_details into Company profile field_select_questionnaire.
	 *
	 * Returns [created_count, updated_count, skipped_count].
	 */
	public function syncCompanyQuestionnaireAssignments(): array {
  if (!\Drupal::moduleHandler()->moduleExists('profile')) {
    $this->logger->warning('Profile module not enabled; cannot attach questionnaire assignments.');
    return [0, 0, 0];
  }

  $created = 0; $updated = 0; $skipped = 0;

  $result = $this->legacy->select('qs_company_questionnaire_details', 'cq')
    ->fields('cq', [
      'company_questionnaire_details_id',
      'company_id',
      'questionnaire_id',
      'number_of_meetings',
      'ip_address',
      'user_id',
      'created_date',
      'hide',
      'percentage',
      'date_cron',
      'time_cron',
      'user_timezone',
      'collect_name',
    ])
    ->execute();

  foreach ($result as $row) {
    $company_legacy = (int) ($row->company_id ?? 0);
    $qid_legacy     = (int) ($row->questionnaire_id ?? 0);
    $assign_id      = (int) ($row->company_questionnaire_details_id ?? 0);

    if ($company_legacy <= 0 || $qid_legacy <= 0) { $skipped++; continue; }

    // 1) Map company legacy -> Drupal UID.
    $company_uid = $this->lookupUid('company', $company_legacy);
    if ($company_uid <= 0) {
      $this->logger->warning('No Drupal user mapped for company legacy @c; skipping assignment.', ['@c' => $company_legacy]);
      $skipped++; continue;
    }

    // 2) Load or create the Company profile.
    $profile = $this->loadOrCreateCompanyProfile($company_uid);
    if (!$profile) { $skipped++; continue; }

    // 3) Get (or create) the Questionnaire node for this legacy questionnaire_id.
    $qnode = $this->getQuestionnaireNodeByLegacyId($qid_legacy);
    if (!$qnode) {
      $this->logger->warning('Questionnaire node missing for legacy id @q; skipping.', ['@q' => $qid_legacy]);
      $skipped++; continue;
    }

    // 4) Collect Job Position TIDs for THIS assignment (may be multiple).
    $job_tids = $this->getAssignmentJobPositionTids($assign_id); // ← NEW
    // Values to set/update on the paragraph.
    $hide     = !empty($row->hide) ? 1 : 0;
    $meetings = isset($row->number_of_meetings) ? (int) $row->number_of_meetings : NULL;

    // Helper to upsert one paragraph for a given job_tid (0 means none).
    $upsertOne = function(int $job_tid) use (&$created, &$updated, &$skipped, $profile, $qnode, $hide, $meetings) {
      $existing = $this->findExistingAssignParagraph($profile, (int) $qnode->id(), $job_tid);

      if ($existing) {
        $changed = false;
        if ($existing->hasField('field_hide') && (int)$existing->get('field_hide')->value !== $hide) {
          $existing->set('field_hide', $hide); $changed = true;
        }
        if ($meetings !== NULL && $existing->hasField('field_number_of_meetings')) {
          $cur = (int) ($existing->get('field_number_of_meetings')->value ?? 0);
          if ($cur !== $meetings) { $existing->set('field_number_of_meetings', $meetings); $changed = true; }
        }
        if ($existing->hasField('field_questionnaire')) {
          $curN = (int) ($existing->get('field_questionnaire')->target_id ?? 0);
          if ($curN !== (int) $qnode->id()) {
            $existing->set('field_questionnaire', ['target_id' => (int) $qnode->id()]);
            $changed = true;
          }
        }
        if ($job_tid && $existing->hasField('field_job_position')) {
          $curT = (int) ($existing->get('field_job_position')->target_id ?? 0);
          if ($curT !== $job_tid) {
            $existing->set('field_job_position', ['target_id' => $job_tid]);
            $changed = true;
          }
        }

        if ($changed) {
          $existing->save();
          $this->refreshParagraphRefOnProfile($profile, $existing);
          $profile->save();
          $updated++;
        } else {
          $skipped++;
        }
        return;
      }

      // Create new paragraph.
      $p = \Drupal\paragraphs\Entity\Paragraph::create([
        'type' => $this->getAssignParaType(),
      ]);
      if ($p->hasField('field_hide')) {
        $p->set('field_hide', $hide);
      }
      if ($p->hasField('field_number_of_meetings') && $meetings !== NULL) {
        $p->set('field_number_of_meetings', $meetings);
      }
      if ($p->hasField('field_questionnaire')) {
        $p->set('field_questionnaire', ['target_id' => (int) $qnode->id()]);
      }
      if ($job_tid && $p->hasField('field_job_position')) {
        $p->set('field_job_position', ['target_id' => $job_tid]);
      }
      $p->save();

      // Append to profile field.
      $items = $profile->get($this->getCompanySelectField())->getValue() ?? [];
      $items[] = ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];
      $profile->set($this->getCompanySelectField(), $items);
      $profile->save();
      $created++;
    };

    if ($job_tids) {
      foreach (array_values(array_unique($job_tids)) as $tid) {
        if ($tid > 0) { $upsertOne((int) $tid); }
      }
    } else {
      // No job positions linked -> upsert a generic assignment (no job position).
      $upsertOne(0);
    }
  }

  return [$created, $updated, $skipped];
}


protected function getAssignmentJobPositionTids(int $assignment_id): array {
  if ($assignment_id <= 0) { return []; }

  // Build query step-by-step; keep $query as the query object.
  $query = $this->legacy->select('qs_company_jobprofilerelation', 'r');
  $query->fields('r', ['job_position_id']);

  // leftJoin() RETURNS THE ALIAS STRING — store it separately.
  $j_alias = $query->leftJoin('qs_job_position', 'j', 'j.job_position_id = r.job_position_id');

  // addField() ALSO RETURNS A STRING (the field alias) — DO NOT chain off it.
  $field_alias = $query->addField($j_alias, 'job_position_name', 'job_position_name');

  $query->condition('r.company_questionnaire_details_id', $assignment_id);

  $res = $query->execute();

  $tids = [];
  foreach ($res as $row) {
    $name = $this->utf((string) ($row->job_position_name ?? ''));
    if ($name !== '') {
      if ($tid = $this->loadTermTidByName($this->getJobPositionVocab(), $name)) {
        $tids[] = (int) $tid;
      }
    }
  }
  return array_values(array_unique(array_filter($tids)));
}

/**
 * Load a term TID by name within a vocabulary.
 * If $create_if_missing is TRUE, it will create the term and return its TID.
 */
protected function loadTermTidByName(string $vocab, string $name, bool $create_if_missing = FALSE): int {
  $name = $this->utf($name);
  $name = preg_replace('/\s+/u', ' ', trim($name));
  if ($name === '') { return 0; }

  $storage = $this->etm->getStorage('taxonomy_term');

  // Exact match first.
  $tids = $storage->getQuery()
    ->condition('vid', $vocab)
    ->condition('name', $name)
    ->accessCheck(FALSE)
    ->range(0, 1)
    ->execute();

  if ($tids) {
    return (int) reset($tids);
  }

  // Case-insensitive / loose fallback (useful if DB collation surprises).
  $tids = $storage->getQuery()
    ->condition('vid', $vocab)
    ->condition('name', $name, 'LIKE')
    ->accessCheck(FALSE)
    ->range(0, 1)
    ->execute();

  if ($tids) {
    return (int) reset($tids);
  }

  // Optionally create the term if it's missing.
  if ($create_if_missing) {
    $term = Term::create([
      'vid'  => $vocab,
      'name' => $name,
    ]);
    $term->save();
    return (int) $term->id();
  }

  return 0;
}



	/* ---------------------- helpers for mapping ---------------------- */

	/** Field machine names / types in one place (adjust if yours differ). */
	protected function getCompanySelectField(): string {
	  // Company Profile field that holds the Assign Questionnaire paragraphs.
	  return 'field_select_questionnaire';
	}
	protected function getAssignParaType(): string {
	  // Paragraph type machine name for "Assign Questionnaire".
	  return 'assign_questionnaire';
	}
	protected function getQuestionnaireBundle(): string {
	  // Node type machine name for Questionnaire content.
	  return 'questionnaire';
	}
	protected function getJobPositionVocab(): string {
	  return 'job_position';
	}

	/**
	 * Load or create the Company profile for a UID.
	 */
	protected function loadOrCreateCompanyProfile(int $uid) {
	  /** @var \Drupal\profile\ProfileStorageInterface $storage */
	  $storage = $this->etm->getStorage('profile');
	  $pids = $storage->getQuery()
		->condition('type', 'company')
		->condition('uid', $uid)
		->accessCheck(FALSE)
		->range(0, 1)
		->execute();
	  $profile = $pids ? $storage->load(reset($pids)) : NULL;
	  if (!$profile) {
		$profile = \Drupal\profile\Entity\Profile::create([
		  'type' => 'company',
		  'uid' => $uid,
		  'status' => 1,
		]);
		$profile->save();
	  }
	  return $profile;
	}

	/**
	 * Find an existing Assign Questionnaire paragraph on the profile
	 * that matches questionnaire NID (and job TID if provided).
	 */
	protected function findExistingAssignParagraph($profile, int $questionnaire_nid, int $job_tid = 0): ?\Drupal\paragraphs\Entity\Paragraph {
	  $field = $this->getCompanySelectField();
	  if (!$profile->hasField($field) || $profile->get($field)->isEmpty()) { return NULL; }
	  /** @var \Drupal\Core\Field\EntityReferenceRevisionsFieldItemListInterface $list */
	  $list = $profile->get($field);
	  foreach ($list as $item) {
		/** @var \Drupal\paragraphs\Entity\Paragraph|null $p */
		$p = $item->entity ?? NULL;
		if (!$p || $p->bundle() !== $this->getAssignParaType()) { continue; }

		$match_q = false;
		if ($p->hasField('field_questionnaire') && !$p->get('field_questionnaire')->isEmpty()) {
		  $match_q = ((int) $p->get('field_questionnaire')->target_id === $questionnaire_nid);
		}
		if (!$match_q) { continue; }

		if ($job_tid && $p->hasField('field_job_position')) {
		  $cur_tid = (int) ($p->get('field_job_position')->target_id ?? 0);
		  if ($cur_tid !== $job_tid) { continue; }
		}
		return $p;
	  }
	  return NULL;
	}

	/**
	 * Ensure the profile's field points to the latest paragraph revision (after updates).
	 */
	protected function refreshParagraphRefOnProfile($profile, \Drupal\paragraphs\Entity\Paragraph $para): void {
	  $field = $this->getCompanySelectField();
	  if (!$profile->hasField($field) || $profile->get($field)->isEmpty()) { return; }
	  $items = $profile->get($field)->getValue();
	  $changed = false;
	  foreach ($items as &$item) {
		if ((int)$item['target_id'] === (int)$para->id()) {
		  $item['target_revision_id'] = $para->getRevisionId();
		  $changed = true;
		}
	  }
	  if ($changed) { $profile->set($field, $items); }
	}

	/**
	 * Get (or create) the Questionnaire node for a legacy questionnaire_id.
	 * Uses legacy table to fetch the title (with SQL-layer repair), then reuses ensureQuestionnaireNode().
	 */
	protected function getQuestionnaireNodeByLegacyId(int $qid_legacy): ?\Drupal\node\Entity\Node {
	  // Pull the title with mojibake repair alias.
	  $q = $this->legacy->select('qs_questionnaire_master', 'qm')
		->fields('qm', ['questionnaire_id', 'questionnaire_name'])
		->condition('qm.questionnaire_id', $qid_legacy)
		->range(0, 1);
	  $q->addExpression(
		"CONVERT(CAST(CONVERT(qm.questionnaire_name USING latin1) AS BINARY) USING utf8mb4)",
		'questionnaire_name_fixed'
	  );
	  $row = $q->execute()->fetchObject();
	  if (!$row) { return NULL; }

	  $title = $this->utf((string)($row->questionnaire_name ?? ''), (string)($row->questionnaire_name_fixed ?? ''));
	  if ($title === '') { $title = "Questionnaire #$qid_legacy"; }

	  // Reuse your existing ensureQuestionnaireNode() (by title) if present:
	  if (method_exists($this, 'ensureQuestionnaireNode')) {
		[$node] = $this->ensureQuestionnaireNode($qid_legacy, $title);
		return $node;
	  }

	  // Otherwise, lookup by title or create.
	  $storage = $this->etm->getStorage('node');
	  $existing = $storage->loadByProperties(['type' => $this->getQuestionnaireBundle(), 'title' => $title]);
	  if ($existing) { return reset($existing); }

	  $node = \Drupal\node\Entity\Node::create([
		'type' => $this->getQuestionnaireBundle(),
		'title' => $title,
		'status' => 1,
	  ]);
	  $node->save();
	  return $node;
	}

	/**
	 * Resolve Job Position term TID from the legacy row.
	 * Supports: job_position_id (tid), or job_position_name -> vocab term.
	 */
	protected function resolveJobPositionTid(object $row): int {
	  // Case 1: a direct tid is present in legacy row.
	  if (isset($row->job_position_id) && ctype_digit((string)$row->job_position_id)) {
		return (int)$row->job_position_id;
	  }

	  // Case 2: try by name if available.
	  $name = '';
	  if (isset($row->job_position_name) && trim((string)$row->job_position_name) !== '') {
		$name = $this->utf((string)$row->job_position_name);
	  }
	  if ($name !== '') {
		if ($tid = $this->loadTermTidByName($this->getJobPositionVocab(), $name)) {
		  return $tid;
		}
	  }
	  return 0;
	}



  
}
