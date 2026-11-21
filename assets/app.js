const topbar = document.querySelector('.topbar');
const menuButton = document.querySelector('.menu-toggle');
const navLinks = document.querySelectorAll('.nav-links a');
if (menuButton && topbar) {
  menuButton.addEventListener('click', () => {
    const isOpen = topbar.classList.toggle('menu-open');
    menuButton.setAttribute('aria-expanded', String(isOpen));
  });

  navLinks.forEach((link) => {
    link.addEventListener('click', () => {
      topbar.classList.remove('menu-open');
      menuButton.setAttribute('aria-expanded', 'false');
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && topbar.classList.contains('menu-open')) {
      topbar.classList.remove('menu-open');
      menuButton.setAttribute('aria-expanded', 'false');
    }
  });
}

const textSizeBtns = document.querySelectorAll('#text-size-toggle, .text-size-sync');

if (textSizeBtns.length) {
  const syncTextSizeLabel = () => {
    const isLarge = document.documentElement.classList.contains('text-large');
    textSizeBtns.forEach((btn) => {
      btn.setAttribute('aria-pressed', String(isLarge));
      btn.innerHTML = isLarge
        ? '<span aria-hidden="true">Aa</span> Cỡ chữ vừa'
        : '<span aria-hidden="true">Aa</span> Cỡ chữ lớn';
    });
  };

  syncTextSizeLabel();

  textSizeBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      const isLarge = document.documentElement.classList.toggle('text-large');
      localStorage.setItem('hh-text-size', isLarge ? 'large' : 'normal');
      syncTextSizeLabel();
    });
  });
}
