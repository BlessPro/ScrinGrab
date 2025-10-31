(function (window, document) {
  'use strict';

  const config = window.SCRIPGRAB || {};
  const captureSeed = normalizeSelection(config.captureSelection);
  const scheduleSeed = new Set(
    Array.isArray(config.schedulePages) ? config.schedulePages.filter(Boolean).map(String) : []
  );

  const restBase = resolveRestBase(config.rest);
  const restHeaders = resolveRestHeaders(config.nonce);

  const tabs = Array.from(document.querySelectorAll('[data-sg-tabs] .sg-tab'));
  const panels = Array.from(document.querySelectorAll('.sg-tab-panel'));

  function normalizeSelection(source) {
    const devices = ['desktop', 'tablet', 'mobile'];
    const map = {};
    devices.forEach((device) => {
      if (source && Array.isArray(source[device])) {
        map[device] = source[device].filter(Boolean).map(String);
      } else {
        map[device] = [];
      }
    });
    return map;
  }

  function resolveRestBase(restUrl) {
    if (typeof restUrl === 'string' && restUrl.length) {
      return restUrl.replace(/\/$/, '');
    }
    if (window.wpApiSettings && window.wpApiSettings.root) {
      return (window.wpApiSettings.root.replace(/\/$/, '') || '') + '/scripgrab/v1';
    }
    return '';
  }

  function resolveRestHeaders(nonce) {
    const headers = {};
    const value = nonce || (window.wpApiSettings && window.wpApiSettings.nonce);
    if (value) headers['X-WP-Nonce'] = value;
    return headers;
  }

  function escapeHTML(value) {
    return (value || '').replace(/[&<>"']/g, (char) => {
      switch (char) {
        case '&': return '&amp;';
        case '<': return '&lt;';
        case '>': return '&gt;';
        case '"': return '&quot;';
        case "'": return '&#39;';
        default: return char;
      }
    });
  }

  function deviceLabel(device) {
    switch (device) {
      case 'tablet': return 'Tablet';
      case 'mobile': return 'Mobile';
      default: return 'Desktop';
    }
  }

  function updateQueryParam(key, value) {
    const url = new URL(window.location.href);
    if (value) {
      url.searchParams.set(key, value);
    } else {
      url.searchParams.delete(key);
    }
    window.history.replaceState({}, '', url.toString());
  }

  function requestPreview(url, device) {
    if (!restBase) {
      return Promise.reject(new Error('REST endpoint unavailable'));
    }

    const params = new URLSearchParams({ url: String(url || '') });
    if (device) params.append('device', device);
    const endpoint = restBase + '/preview?' + params.toString();

    if (window.wp && window.wp.apiFetch) {
      return window.wp.apiFetch({
        url: endpoint,
        headers: restHeaders,
      });
    }

    return window.fetch(endpoint, {
      credentials: 'same-origin',
      headers: restHeaders,
    }).then((response) => {
      if (!response.ok) throw new Error('Preview request failed');
      return response.json();
    });
  }

  function createPreviewController(previewBox) {
    if (!previewBox) return null;

    const placeholder = previewBox.querySelector('[data-sg-preview-placeholder]');
    const previewImg = previewBox.querySelector('[data-sg-preview-img]');
    const defaultMarkup = placeholder ? placeholder.innerHTML : '';
    let currentToken = null;

    const showPlaceholder = (html) => {
      if (!placeholder) return;
      placeholder.innerHTML = html;
      placeholder.style.display = '';
      if (previewImg) previewImg.style.display = 'none';
    };

    const showDefault = () => {
      if (!placeholder) return;
      placeholder.innerHTML = defaultMarkup;
      placeholder.style.display = '';
      if (previewImg) previewImg.style.display = 'none';
    };

    const showImage = (src, alt) => {
      if (!previewImg) return;
      previewImg.src = src;
      if (alt) previewImg.alt = alt;
      previewImg.style.display = 'block';
      if (placeholder) placeholder.style.display = 'none';
    };

    const setLoading = (title) => {
      const detail = title ? 'Fetching ' + escapeHTML(title) + '…' : 'Fetching preview…';
      showPlaceholder('<strong>Loading preview...</strong><p class="sg-small">' + detail + '</p>');
    };

    const setError = () => {
      showPlaceholder('<strong>Preview unavailable</strong><p class="sg-small">We could not load this preview yet.</p>');
    };

    const load = (pageUrl, device, title) => {
      if (!pageUrl) {
        showDefault();
        return;
      }

      const token = Symbol('preview');
      currentToken = token;
      setLoading(title);

      requestPreview(pageUrl, device)
        .then((payload) => {
          if (token !== currentToken) return;
          if (payload && payload.image) {
            const alt = title
              ? title + ' (' + deviceLabel(device) + ') preview'
              : deviceLabel(device) + ' preview';
            showImage(payload.image, alt);
          } else {
            setError();
          }
        })
        .catch(() => {
          if (token !== currentToken) return;
          setError();
        });
    };

    return {
      load,
      reset: showDefault,
    };
  }

  function activateTab(tabName) {
    const target = tabName || 'capture';
    tabs.forEach((tab) => {
      const isActive = tab.getAttribute('data-tab') === target;
      tab.classList.toggle('active', isActive);
    });
    panels.forEach((panel) => {
      const isActive = panel.getAttribute('data-tab-panel') === target;
      panel.classList.toggle('active', isActive);
    });
  }

  if (tabs.length && panels.length) {
    const urlParams = new URL(window.location.href).searchParams;
    const initialTab = urlParams.get('sg_tab') || config.activeTab || 'capture';
    activateTab(initialTab);

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        const name = tab.getAttribute('data-tab') || 'capture';
        activateTab(name);
        updateQueryParam('sg_tab', name);
      });
    });
  }

  document.querySelectorAll('[data-sg-context]').forEach((container) => {
    const context = container.getAttribute('data-sg-context');
    if (context === 'capture') {
      setupCapture(container);
    } else if (context === 'settings') {
      setupSettings(container);
    }
  });

  function setupCapture(container) {
    const pageList = container.querySelector('[data-sg-page-list]');
    if (!pageList) return;

    const previewBox = container.querySelector('[data-sg-preview]');
    const preview = createPreviewController(previewBox);
    const selectAll = container.querySelector('[data-sg-select-all]');
    const deviceGroup = container.querySelector('[data-sg-device-group]');

    const memory = {
      desktop: new Set(captureSeed.desktop),
      tablet: new Set(captureSeed.tablet),
      mobile: new Set(captureSeed.mobile),
    };

    let activeDevice = 'desktop';
    const allowedDevices = ['desktop', 'tablet', 'mobile'];

    const getCheckboxes = () => Array.from(pageList.querySelectorAll('.sg-page-checkbox'));

    const updateSelectAllState = () => {
      if (!selectAll) return;
      const boxes = getCheckboxes();
      if (!boxes.length) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
        return;
      }
      const checkedCount = boxes.filter((box) => box.checked).length;
      selectAll.checked = checkedCount === boxes.length;
      selectAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
    };

    const loadFirstChecked = () => {
      const first = getCheckboxes().find((box) => box.checked);
      if (first) {
        const url = first.dataset.url || first.value || '';
        if (url) {
          preview && preview.load(url, activeDevice, first.dataset.title || first.value);
          return;
        }
      }
      preview && preview.reset();
    };

    const syncFromMemory = () => {
      const remembered = memory[activeDevice] || new Set();
      getCheckboxes().forEach((box) => {
        const url = box.dataset.url || box.value || '';
        box.checked = remembered.has(url);
      });
      updateSelectAllState();
      loadFirstChecked();
    };

    const handleCheckboxChange = (box) => {
      const url = box.dataset.url || box.value || '';
      if (!url) return;
      const label = box.dataset.title || box.value || '';
      const set = memory[activeDevice] || new Set();

      if (box.checked) {
        set.add(url);
      } else {
        set.delete(url);
      }
      memory[activeDevice] = set;

      updateSelectAllState();

      if (box.checked) {
        preview && preview.load(url, activeDevice, label);
      } else {
        loadFirstChecked();
      }
    };

    if (deviceGroup) {
      deviceGroup.addEventListener('change', (event) => {
        const radio = event.target.closest('input[type="radio"]');
        if (!radio) return;
        const selected = radio.value;
        if (!allowedDevices.includes(selected)) return;
        activeDevice = selected;
        syncFromMemory();
      });
    }

    pageList.addEventListener('change', (event) => {
      const box = event.target.closest('.sg-page-checkbox');
      if (box) {
        handleCheckboxChange(box);
      }
    });

    if (selectAll) {
      selectAll.addEventListener('change', () => {
        const shouldCheck = selectAll.checked;
        const set = memory[activeDevice] || new Set();
        getCheckboxes().forEach((box) => {
          box.checked = shouldCheck;
          const url = box.dataset.url || box.value || '';
          if (!url) return;
          if (shouldCheck) {
            set.add(url);
          } else {
            set.delete(url);
          }
        });
        memory[activeDevice] = set;
        updateSelectAllState();
        if (shouldCheck) {
          loadFirstChecked();
        } else {
          preview && preview.reset();
        }
      });
    }

    syncFromMemory();
  }

  function setupSettings(container) {
    const pageList = container.querySelector('[data-sg-page-list]');
    if (!pageList) return;

    const previewBox = container.querySelector('[data-sg-preview]');
    const preview = createPreviewController(previewBox);
    const selectAll = container.querySelector('[data-sg-select-all]');
    const device = previewBox ? previewBox.getAttribute('data-sg-preview-device') || 'desktop' : 'desktop';
    const selection = new Set(scheduleSeed);

    const getCheckboxes = () => Array.from(pageList.querySelectorAll('.sg-page-checkbox'));

    const updateSelectAllState = () => {
      if (!selectAll) return;
      const boxes = getCheckboxes();
      if (!boxes.length) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
        return;
      }
      const checkedCount = boxes.filter((box) => box.checked).length;
      selectAll.checked = checkedCount === boxes.length;
      selectAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
    };

    const loadFirstChecked = () => {
      const first = getCheckboxes().find((box) => box.checked);
      if (first) {
        const url = first.dataset.url || first.value || '';
        if (url) {
          preview && preview.load(url, device, first.dataset.title || first.value);
          return;
        }
      }
      preview && preview.reset();
    };

    pageList.addEventListener('change', (event) => {
      const box = event.target.closest('.sg-page-checkbox');
      if (!box) return;
      const url = box.dataset.url || box.value || '';
      if (!url) return;

      if (box.checked) {
        selection.add(url);
        preview && preview.load(url, device, box.dataset.title || box.value);
      } else {
        selection.delete(url);
        loadFirstChecked();
      }

      updateSelectAllState();
    });

    if (selectAll) {
      selectAll.addEventListener('change', () => {
        const shouldCheck = selectAll.checked;
        getCheckboxes().forEach((box) => {
          box.checked = shouldCheck;
          const url = box.dataset.url || box.value || '';
          if (!url) return;
          if (shouldCheck) {
            selection.add(url);
          } else {
            selection.delete(url);
          }
        });

        updateSelectAllState();

        if (shouldCheck) {
          loadFirstChecked();
        } else {
          preview && preview.reset();
        }
      });
    }

    updateSelectAllState();
    loadFirstChecked();
  }
})(window, document);
