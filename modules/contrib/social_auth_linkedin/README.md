# Social Auth LinkedIn

Social Auth LinkedIn is a LinkedIn authentication integration for Drupal. It is
based on the Social Auth and Social API projects

It adds to the site:

- A new url: `/user/login/linkedin`.
- A settings form at `/admin/config/social-api/social-auth/linkedin`.
- A LinkedIn logo in the Social Auth Login block.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/social_auth_linkedin).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/social_auth_linkedin).


## Table of contents

- Requirements
- Installation
- Configuration
- How it works
- Support requests
- Maintainers


## Requirements

This module requires the following modules:

- [Social Auth](https://drupal.org/project/social_auth)
- [Social API](https://drupal.org/project/social_api)


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

In Drupal:

1. Log in as an admin.
2. Navigate to Configuration » User authentication » LinkedIn and copy
   the Authorized redirect URL field value (the URL should end in
   `/user/login/linkedin/callback`).
   In [LinkedIn Developers](https://developer.linkedin.com/):
3. Log in to a LinkedIn account.
4. Click [Create app](https://www.linkedin.com/developers/apps/new).
5. Complete all required fields.
    1. If necessary click Create a new LinkedIn Page to create a company page.
    2. Select Company as the page type.
    3. Fill it all required fields.
    4. Click Create page.
6. Click Create app.
7. Navigate to the Products section.
8. Click Select for the Sign In with LinkedIn product.
9. Read and agree to the terms and click Add product.
10. Navigate to the Auth section.
11. Click the edit icon for the Authorized redirect URLs for your app field and
    click Add redirect URL.
12. Paste the URL copied from Step 2.
13. Click Update.
14. Copy the Client ID and Client Secret field values and save them somewhere
    safe.

In Drupal:

15. Return to Configuration » User authentication » LinkedIn
16. Enter the client ID in the Client ID field.
17. Enter the client secret in the Client secret field.
18. Click Save configuration.
19. Navigate to Structure » Block Layout and place a Social Auth login block
    somewhere on the site (if not already placed).


## How it works

The user can click on the LinkedIn logo on the Social Auth Login block
You can also add a button or link anywhere on the site that points
to `/user/login/linkedin`, so theming and customizing the button or link
is very flexible.

After LinkedIn has returned the user to your site, the module compares the user
id or email address provided by LinkedIn. If the user has previously registered
using LinkedIn or your site already has an account with the same email address,
the user is logged in. If not, a new user account is created. Also, a LinkedIn
account can be associated with an authenticated user.


## Support requests

- Before posting a support request, carefully read the installation
  instructions provided in module documentation page.
- Before posting a support request, check the Recent Log entries at
  `admin/reports/dblog`
- Once you have done this, you can post a support request at module issue
  queue: [https://www.drupal.org/project/issues/social_auth_linkedin](https://www.drupal.org/project/issues/social_auth_linkedin)
- When posting a support request, please inform if you were able to see any
  errors in the Recent Log entries.


## Maintainers

- Christopher C. Wells - [wells](https://www.drupal.org/u/wells)
- Getulio Valentin Sánchez - [gvso](https://www.drupal.org/u/gvso)
- Himanshu Dixit - [himanshu-dixit](https://www.drupal.org/u/himanshu-dixit)
- Adrian Gheorghe - [adrianghe](https://www.drupal.org/u/adrianghe)

**Development sponsored by:**
- [Cascade Public Media](https://www.drupal.org/cascade-public-media)
