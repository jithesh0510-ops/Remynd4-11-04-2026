document.addEventListener('DOMContentLoaded', function () {
  const togglers = document.querySelectorAll('.sidebar ul li > span');

  // Setup collapsible togglers
  togglers.forEach((toggle) => {
    const parentLi = toggle.closest('li');
    const childUl = parentLi.querySelector('ul');

    if (childUl) {
      parentLi.classList.add('has-children');
      childUl.style.display = 'none';

      toggle.addEventListener('click', function () {
        const isOpen = parentLi.classList.toggle('open');
        childUl.style.display = isOpen ? 'block' : 'none';
      });
    }
  });

  // Expand parent .has-children for any <a> with .is-active
  const activeLink = document.querySelector('.sidebar a.is-active');

  if (activeLink) {
    let currentLi = activeLink.closest('li');

    if (currentLi) {
      currentLi.classList.add('active');

      // Traverse up to open all parents with .has-children
      while (currentLi) {
        const parentUl = currentLi.closest('ul');
        const parentLi = parentUl ? parentUl.closest('li.has-children') : null;

        if (parentLi) {
          parentLi.classList.add('open');

          const submenu = parentLi.querySelector('ul');
          if (submenu) {
            submenu.style.display = 'block';
          }
        }

        currentLi = parentLi;
      }
    }
  }
});
