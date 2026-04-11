// Theme toggle logic for dark/light mode
document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.querySelector('#theme-toggle');
  if (toggle) {
    toggle.addEventListener('click', () => {
      document.body.dataset.theme =
        document.body.dataset.theme === 'dark' ? 'default' : 'dark';
    });
  }
});
