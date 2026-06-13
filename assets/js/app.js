document.querySelectorAll('.alert').forEach((alertBox) => {
  globalThis.setTimeout(() => {
    alertBox.style.transition = 'opacity 0.35s ease';
    alertBox.style.opacity = '0';
    globalThis.setTimeout(() => {
      alertBox.remove();
    }, 380);
  }, 4200);
});

document.querySelectorAll('.toggle-visibility').forEach((button) => {
  button.addEventListener('click', () => {
    const field = button.closest('.toggle-field');
    const input = field ? field.querySelector('input') : null;
    if (!input) {
      return;
    }

    const isHidden = input.getAttribute('type') === 'password';
    input.setAttribute('type', isHidden ? 'text' : 'password');
    button.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
    button.classList.toggle('is-visible', isHidden);
  });
});
