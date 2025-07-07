// ImageModal: Tái sử dụng cho mọi ảnh có data-zoom
(function() {
  if (window.ImageModalManager) return;

  function createModal() {
    let overlay = document.createElement('div');
    overlay.className = 'image-modal-overlay';
    overlay.style.display = 'none';
    overlay.innerHTML = `
      <div class="image-modal-content">
        <button class="image-modal-close" type="button">&times;</button>
        <img src="" alt="Full size" />
      </div>
    `;
    document.body.appendChild(overlay);
    return overlay;
  }

  const modal = createModal();
  const modalContent = modal.querySelector('.image-modal-content');
  const modalImg = modal.querySelector('img');
  const closeBtn = modal.querySelector('.image-modal-close');

  function openModal(src) {
    modalImg.src = src;
    modal.style.display = 'flex';
    setTimeout(() => {
      modalContent.classList.add('show');
      modalContent.classList.remove('closing');
    }, 10);
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    modalContent.classList.remove('show');
    modalContent.classList.add('closing');
    setTimeout(() => {
      modal.style.display = 'none';
      modalImg.src = '';
      document.body.style.overflow = '';
    }, 250);
  }

  // Click overlay hoặc nút đóng
  modal.addEventListener('click', function(e) {
    if (e.target === modal || e.target === closeBtn) closeModal();
  });
  // ESC
  window.addEventListener('keydown', function(e) {
    if (modal.style.display === 'flex' && (e.key === 'Escape' || e.keyCode === 27)) closeModal();
  });

  // Lắng nghe click trên mọi img[data-zoom]
  document.addEventListener('click', function(e) {
    const img = e.target.closest('img[data-zoom]');
    if (img) {
      openModal(img.src);
    }
  });

  // Expose for manual use if needed
  window.ImageModalManager = {
    open: openModal,
    close: closeModal
  };
})(); 