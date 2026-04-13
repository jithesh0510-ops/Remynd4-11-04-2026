(function ($, Drupal) {
  Drupal.behaviors.ginChildBehavior = {
    attach: function (context, settings) {
      console.log("Gin Child Theme JS loaded.");

      // Extract options only once
      const options = [];
      $('.field--name-field-option-title.field__item', context).each(function () {
        const text = $(this).text().trim();
        if (text && !options.includes(text)) {
          options.push(text);
        }
      });

      // Inject radios after each question block
      $('.paragraph--type--question', context).each(function (index) {
        const $question = $(this);

        // Prevent duplication
        if ($question.next('.questionnaire-radio-options').length === 0) {
          const $radioWrapper = $('<div class="questionnaire-radio-options d-flex flex-wrap gap-2 mt-2"></div>');

          options.forEach(option => {
            const $label = $(`
              <label class="me-3">
                <input type="radio" name="question_${index + 1}" value="${option}" />
                ${option}
              </label>
            `);
            $radioWrapper.append($label);
          });

          $question.after($radioWrapper);
        }
      });
    }
  };
})(jQuery, Drupal);
