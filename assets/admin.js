(function(){
  // tabs
  const tabs = document.querySelectorAll('[data-sg-tabs] .sg-tab');
  const panels = document.querySelectorAll('.sg-tab-panel');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const target = tab.getAttribute('data-tab');
      tabs.forEach(t => t.classList.remove('active'));
      panels.forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      document.querySelector(`[data-tab-panel="${target}"]`).classList.add('active');
    });
  });

  // preview (later we’ll call REST; now just show placeholder)
  const pageList = document.querySelector('[data-sg-page-list]');
  const previewImg = document.querySelector('.sg-preview-img');
  if (pageList && previewImg) {
    pageList.addEventListener('click', (e) => {
      const cb = e.target.closest('.sg-page-checkbox');
      if (!cb) return;
      // for now, show placeholder
      previewImg.src = 'https://via.placeholder.com/800x450?text=ScripGrab+Preview';
      previewImg.style.display = 'block';
    });
  }
})();
