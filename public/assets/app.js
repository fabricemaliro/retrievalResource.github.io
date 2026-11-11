// Progressive enhancement only: every page works without JavaScript.
document.addEventListener('DOMContentLoaded', () => {
  // Auto-apply filters and sorting when a checkbox or select changes
  document.querySelectorAll('[data-autosubmit]').forEach((el) => {
    el.addEventListener('change', () => el.form && el.form.submit());
  });

  // "Show all" for long facet lists
  document.querySelectorAll('[data-show-more]').forEach((btn) => {
    btn.addEventListener('click', () => {
      btn.closest('fieldset').classList.add('expanded');
      btn.remove();
    });
  });

  // Filters drawer on small screens
  const toggle = document.querySelector('[data-toggle-filters]');
  const filters = document.getElementById('filters');
  if (toggle && filters) {
    toggle.addEventListener('click', () => {
      const open = filters.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(open));
    });
  }

  // Press "/" to jump to the search box
  document.addEventListener('keydown', (e) => {
    const box = document.getElementById('q');
    if (e.key === '/' && box && document.activeElement !== box && !/input|textarea|select/i.test(document.activeElement.tagName)) {
      e.preventDefault();
      box.focus();
      box.select();
    }
  });
});
