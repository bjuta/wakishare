(function(){
  var adminConfig = window.yourShareAdmin || {};

  function qs(root, selector){
    return root ? root.querySelector(selector) : null;
  }

  function qsa(root, selector){
    return root ? Array.prototype.slice.call(root.querySelectorAll(selector)) : [];
  }

  function formatNumber(value){
    var number = parseInt(value, 10);
    if (!isFinite(number)){
      number = 0;
    }
    if (typeof number.toLocaleString === 'function'){
      return number.toLocaleString();
    }
    return String(number);
  }

  function init(){
    var root = document.querySelector('[data-your-share-admin]');
    if (!root){
      return;
    }

    setupTabs(root);
    setupNetworkPicker(root);
    setupReactionPicker(root);
    setupShortcodePreview(root);
    setupFollowShortcodePreview(root);
    setupUtmPreview(root);
    setupAnalyticsReports(root);
  }

  function setupReactionPicker(root){
    var container = qs(root, '[data-your-share-reaction-picker]');
    var list = qs(root, '[data-your-share-reaction-list]');
    if (!container || !list){
      return;
    }

    var manager = qs(root, '[data-your-share-reaction-manager]');
    var selectedList = manager ? qs(manager, '[data-your-share-reaction-selected]') : null;
    var emptyState = manager ? qs(manager, '[data-your-share-reaction-empty]') : null;
    var total = manager ? qs(manager, '[data-your-share-reaction-total]') : null;
    var library = manager ? qs(manager, '[data-your-share-reaction-library]') : null;
    var search = qs(container, '[data-your-share-reaction-search]');
    if (!search){
      return;
    }

    var options = qsa(list, '[data-reaction-slug]');

    function renderSummary(){
      if (!selectedList && !emptyState && !total){
        return;
      }

      var activeOptions = options.filter(function(option){
        var input = option.querySelector('input[type="checkbox"]');
        return input && input.checked;
      });

      if (selectedList){
        selectedList.innerHTML = '';
      }

      if (total){
        var template = total.getAttribute('data-template') || '%s';
        total.textContent = template.replace('%s', String(activeOptions.length));
      }

      if (!activeOptions.length){
        if (emptyState){
          emptyState.hidden = false;
        }
        return;
      }

      if (emptyState){
        emptyState.hidden = true;
      }

      if (!selectedList){
        return;
      }

      activeOptions.forEach(function(option){
        var slug = option.getAttribute('data-reaction-slug') || '';
        var label = option.getAttribute('data-reaction-name') || slug;
        var symbol = option.getAttribute('data-reaction-symbol') || '';
        var image = option.getAttribute('data-reaction-image') || '';
        var chip = document.createElement('span');
        chip.className = 'your-share-reaction-chip';
        chip.setAttribute('data-reaction-slug', slug);

        var icon = document.createElement('span');
        icon.className = 'your-share-reaction-chip__symbol';
        if (image){
          icon.className += ' has-image';
          var img = document.createElement('img');
          img.src = image;
          img.alt = '';
          img.setAttribute('role', 'presentation');
          icon.appendChild(img);
        } else {
          icon.textContent = symbol;
        }

        var text = document.createElement('span');
        text.className = 'your-share-reaction-chip__text';
        text.textContent = label;

        chip.appendChild(icon);
        chip.appendChild(text);
        selectedList.appendChild(chip);
      });
    }

    function filter(){
      var term = search.value ? search.value.toLowerCase().trim() : '';
      options.forEach(function(option){
        var label = option.getAttribute('data-reaction-label') || '';
        var slug = option.getAttribute('data-reaction-slug') || '';
        var match = !term || label.indexOf(term) !== -1 || slug.indexOf(term) !== -1;
        option.style.display = match ? '' : 'none';
      });
    }

    options.forEach(function(option){
      var input = option.querySelector('input[type="checkbox"]');
      if (!input){
        return;
      }

      option.classList.toggle('is-active', input.checked);
      input.addEventListener('change', function(){
        option.classList.toggle('is-active', input.checked);
        renderSummary();
      });
    });

    if (library && typeof library.addEventListener === 'function'){
      library.addEventListener('toggle', function(){
        if (library.open && search && typeof search.focus === 'function'){
          setTimeout(function(){
            try {
              search.focus();
            } catch (error) {}
          }, 0);
        }
      });
    }

    renderSummary();
    search.addEventListener('input', filter);
    search.addEventListener('keyup', filter);
    filter();
  }

  function setupAnalyticsReports(root){
    var container = qs(root, '[data-your-share-analytics]');
    if (!container){
      return;
    }

    var chartCanvas = qs(container, '[data-your-share-analytics-chart]');
    var emptyMessage = qs(container, '[data-your-share-analytics-empty]');
    var summaryShare = qs(container, '[data-your-share-analytics-total="share"]');
    var summaryReaction = qs(container, '[data-your-share-analytics-total="reaction"]');
    var updatedEl = qs(container, '[data-your-share-analytics-updated]');
    var tools = qs(container, '[data-your-share-analytics-tools]');
    var notice = tools ? qs(tools, '.your-share-analytics__notice') : null;
    var rangeButtons = qsa(container, '[data-your-share-analytics-ranges] [data-range]');
    var topLists = {
      posts: qs(container, '[data-your-share-analytics-top="posts"]'),
      networks: qs(container, '[data-your-share-analytics-top="networks"]'),
      devices: qs(container, '[data-your-share-analytics-top="devices"]')
    };
    var topEmpty = {
      posts: qs(container, '[data-your-share-analytics-top-empty="posts"]'),
      networks: qs(container, '[data-your-share-analytics-top-empty="networks"]'),
      devices: qs(container, '[data-your-share-analytics-top-empty="devices"]')
    };

    if (!chartCanvas || typeof window.Chart !== 'function'){
      if (emptyMessage){
        emptyMessage.hidden = false;
        emptyMessage.textContent = adminConfig.analytics && adminConfig.analytics.i18n ? (adminConfig.analytics.i18n.error || 'Unable to load analytics data.') : 'Unable to load analytics data.';
      }
      return;
    }

    var state = {
      data: null,
      chart: null,
      range: '7'
    };

    function restRoot(){
      var rest = adminConfig.analytics && adminConfig.analytics.rest ? adminConfig.analytics.rest.root : '';
      if (!rest){
        return '';
      }
      if (rest.charAt(rest.length - 1) === '/'){
        rest = rest.slice(0, -1);
      }
      return rest;
    }

    function setSummary(rangeData){
      if (!rangeData){
        if (summaryShare){ summaryShare.textContent = '0'; }
        if (summaryReaction){ summaryReaction.textContent = '0'; }
        if (emptyMessage){ emptyMessage.hidden = false; }
        return;
      }

      if (summaryShare){
        summaryShare.textContent = formatNumber(rangeData.totals && rangeData.totals.share ? rangeData.totals.share : 0);
      }

      if (summaryReaction){
        summaryReaction.textContent = formatNumber(rangeData.totals && rangeData.totals.reaction ? rangeData.totals.reaction : 0);
      }

      if (emptyMessage){
        var hasData = rangeData.totals && (rangeData.totals.share || rangeData.totals.reaction);
        emptyMessage.hidden = !!hasData;
      }
    }

    function updateUpdated(timestamp){
      if (!updatedEl){
        return;
      }
      if (!timestamp){
        updatedEl.textContent = '';
        return;
      }
      var text = timestamp;
      try {
        var normalized = timestamp.replace(' ', 'T');
        var parsed = new Date(normalized);
        if (!isNaN(parsed.getTime()) && typeof parsed.toLocaleString === 'function'){
          text = parsed.toLocaleString();
        }
      } catch (error) {
        text = timestamp;
      }
      if (adminConfig.analytics && adminConfig.analytics.i18n && adminConfig.analytics.i18n.updated){
        updatedEl.textContent = adminConfig.analytics.i18n.updated.replace('%s', text);
      } else {
        updatedEl.textContent = text;
      }
    }

    function formatNetwork(value){
      if (!value){
        return '';
      }
      return value.replace(/[-_]/g, ' ').replace(/\b\w/g, function(chr){ return chr.toUpperCase(); });
    }

    function populateList(type, items){
      var list = topLists[type];
      var empty = topEmpty[type];
      if (!list){
        return;
      }
      list.innerHTML = '';
      if (!items || !items.length){
        if (empty){
          empty.hidden = false;
        }
        return;
      }
      if (empty){
        empty.hidden = true;
      }
      items.forEach(function(item){
        var li = document.createElement('li');
        var text = '';
        if (type === 'posts'){
          text = item.title || '';
          if (item.link){
            var link = document.createElement('a');
            link.href = item.link;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = text;
            li.appendChild(link);
          } else {
            li.textContent = text;
          }
        } else if (type === 'networks'){
          text = formatNetwork(item.network || '');
          li.textContent = text;
        } else if (type === 'devices'){
          text = item.label || formatNetwork(item.device || '');
          li.textContent = text;
        }
        var value = document.createElement('span');
        value.className = 'your-share-analytics__metric';
        value.textContent = formatNumber(item.total || 0);
        li.appendChild(value);
        list.appendChild(li);
      });
    }

    function datasetConfig(rangeData){
      var palette = {
        share: '#2563eb',
        reaction: '#f97316'
      };
      var shareLabel = adminConfig.analytics && adminConfig.analytics.i18n ? (adminConfig.analytics.i18n.share || 'Shares') : 'Shares';
      var reactionLabel = adminConfig.analytics && adminConfig.analytics.i18n ? (adminConfig.analytics.i18n.reaction || 'Reactions') : 'Reactions';
      return {
        labels: rangeData.labels || [],
        datasets: [
          {
            label: shareLabel,
            data: rangeData.share || [],
            borderColor: palette.share,
            backgroundColor: 'rgba(37, 99, 235, 0.15)',
            tension: 0.35,
            fill: true,
            pointRadius: 2
          },
          {
            label: reactionLabel,
            data: rangeData.reaction || [],
            borderColor: palette.reaction,
            backgroundColor: 'rgba(249, 115, 22, 0.15)',
            tension: 0.35,
            fill: true,
            pointRadius: 2
          }
        ]
      };
    }

    function renderChart(rangeData){
      if (!rangeData){
        return;
      }
      var config = datasetConfig(rangeData);
      if (!state.chart){
        state.chart = new Chart(chartCanvas.getContext('2d'), {
          type: 'line',
          data: config,
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  precision: 0
                }
              }
            },
            plugins: {
              legend: {
                display: true
              }
            }
          }
        });
      } else {
        state.chart.data = config;
        state.chart.update();
      }
    }

    function activateRange(range){
      if (!state.data || !state.data.series || !state.data.series[range]){
        return;
      }
      state.range = range;
      rangeButtons.forEach(function(button){
        var value = button.getAttribute('data-range');
        button.classList.toggle('is-active', value === range);
      });
      var rangeData = state.data.series[range];
      renderChart(rangeData);
      setSummary(rangeData);
    }

    function applyTopLists(data){
      populateList('posts', data.top && data.top.posts ? data.top.posts : []);
      populateList('networks', data.top && data.top.networks ? data.top.networks : []);
      populateList('devices', data.top && data.top.devices ? data.top.devices : []);
    }

    function setToolsEnabled(enabled){
      if (!tools){
        return;
      }
      qsa(tools, 'button').forEach(function(button){
        button.disabled = !enabled;
      });
      if (notice){
        notice.style.display = enabled ? 'none' : '';
      }
    }

    function handleResponse(data){
      state.data = data;
      updateUpdated(data.generated_at || '');
      applyTopLists(data);
      var enabled = !!data.enabled;
      setToolsEnabled(enabled);
      if (!enabled){
        setSummary(null);
        if (notice){
          notice.style.display = '';
        }
        return;
      }
      activateRange(state.range);
    }

    function loadData(){
      var rootUrl = restRoot();
      if (!rootUrl){
        if (emptyMessage){
          emptyMessage.hidden = false;
        }
        return;
      }
      var headers = {};
      if (adminConfig.analytics && adminConfig.analytics.rest && adminConfig.analytics.rest.nonce){
        headers['X-WP-Nonce'] = adminConfig.analytics.rest.nonce;
      }
      fetch(rootUrl + '/analytics/report', {
        credentials: 'same-origin',
        headers: headers
      }).then(function(response){
        if (!response.ok){
          throw new Error('Failed');
        }
        return response.json();
      }).then(function(body){
        handleResponse(body || {});
      }).catch(function(){
        if (emptyMessage){
          emptyMessage.hidden = false;
          emptyMessage.textContent = adminConfig.analytics && adminConfig.analytics.i18n ? (adminConfig.analytics.i18n.error || 'Unable to load analytics data.') : 'Unable to load analytics data.';
        }
      });
    }

    rangeButtons.forEach(function(button){
      button.addEventListener('click', function(){
        var range = button.getAttribute('data-range');
        activateRange(range);
      });
    });

    setToolsEnabled(adminConfig.analytics ? !!adminConfig.analytics.enabled : true);
    loadData();
  }

  function setupTabs(root){
    var tabs = qsa(root, '[data-your-share-tab]');
    var panels = qsa(root, '[data-your-share-panel]');
    var currentInput = qs(root, '[data-your-share-current-tab]');
    var form = qs(root, 'form[data-your-share-form]');
    var refererInput = form ? qs(form, 'input[name="_wp_http_referer"]') : null;
    var refererBase = form ? (form.getAttribute('data-your-share-referer-base') || '') : '';
    if (!refererBase && refererInput){
      refererBase = refererInput.value || '';
    }
    if (!tabs.length || !panels.length || !currentInput){
      return;
    }

    function updateReferer(tab){
      if (!refererInput){
        return;
      }
      var base = refererBase || '';
      if (!base){
        refererInput.value = '';
        return;
      }
      try {
        var url = new URL(base, window.location.origin);
        if (tab){
          url.searchParams.set('tab', tab);
        } else {
          url.searchParams.delete('tab');
        }
        refererInput.value = url.toString();
      } catch (error) {
        var cleaned = base.replace(/([?&])(tab|settings-updated)=[^&#]*/g, '$1').replace(/[?&]$/, '');
        if (tab){
          var separator = cleaned.indexOf('?') === -1 ? '?' : '&';
          refererInput.value = cleaned + separator + 'tab=' + tab;
        } else {
          refererInput.value = cleaned;
        }
      }
    }

    refererBase = (function(base){
      if (!base){
        return '';
      }
      try {
        var url = new URL(base, window.location.origin);
        url.searchParams.delete('tab');
        url.searchParams.delete('settings-updated');
        return url.toString();
      } catch (error) {
        var cleaned = base.replace(/([?&])(tab|settings-updated)=[^&#]*/g, '$1').replace(/[?&]$/, '');
        var origin = window.location && window.location.origin ? window.location.origin : '';
        if (cleaned.indexOf('://') === -1 && origin){
          var needsSlash = cleaned.charAt(0) !== '/';
          cleaned = origin.replace(/\/$/, '') + (needsSlash ? '/' : '') + cleaned;
        }
        return cleaned;
      }
    })(refererBase);

    function activateTab(tab, updateUrl){
      var found = false;
      tabs.forEach(function(link){
        var isActive = link.getAttribute('data-your-share-tab') === tab;
        if (isActive){
          found = true;
        }
        link.classList.toggle('nav-tab-active', isActive);
        link.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      panels.forEach(function(panel){
        var isActive = panel.getAttribute('data-your-share-panel') === tab;
        panel.classList.toggle('is-active', isActive);
        panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
      });

      if (!found && tabs.length){
        tab = tabs[0].getAttribute('data-your-share-tab');
        activateTab(tab, updateUrl);
        return;
      }

      currentInput.value = tab;
      updateReferer(tab);

      if (updateUrl){
        try {
          var url = new URL(window.location.href);
          url.searchParams.set('tab', tab);
          window.history.replaceState({}, '', url.toString());
        } catch (error) {
          // noop
        }
      }
    }

    var initial = (function(){
      try {
        var params = new URLSearchParams(window.location.search);
        return params.get('tab');
      } catch (error) {
        return null;
      }
    })() || currentInput.value || (tabs[0] && tabs[0].getAttribute('data-your-share-tab'));

    tabs.forEach(function(link){
      link.addEventListener('click', function(event){
        event.preventDefault();
        var tab = link.getAttribute('data-your-share-tab');
        if (tab){
          activateTab(tab, true);
        }
      });
    });

    activateTab(initial, false);
  }

  function setupNetworkPicker(root){
    qsa(root, '[data-your-share-networks]').forEach(function(picker){
      var hidden = qs(picker, '[data-your-share-network-input]');
      var list = qs(picker, '[data-your-share-network-list]');
      var count = qs(picker, '[data-your-share-network-count]');
      var buttons = qsa(picker, '.your-share-network-add');
      if (!hidden || !list){
        return;
      }

      function update(){
        var items = qsa(list, '.your-share-network-item');
        var values = items.map(function(item){
          return item.getAttribute('data-value');
        }).filter(Boolean);
        hidden.value = values.join(',');
        if (count){
          count.textContent = values.length;
        }
        refreshShortcodePreview(root);
        refreshFollowShortcodePreview(root);
      }

      function setButtonState(slug, isActive){
        buttons.forEach(function(button){
          if (button.getAttribute('data-value') === slug){
            button.disabled = !!isActive;
            button.classList.toggle('is-active', !!isActive);
          }
        });
      }

      function handleRemove(event){
        var li = event.currentTarget.closest('.your-share-network-item');
        if (!li){
          return;
        }
        var slug = li.getAttribute('data-value');
        li.parentNode.removeChild(li);
        setButtonState(slug, false);
        update();
      }

      function attach(li){
        li.addEventListener('dragstart', function(evt){
          li.classList.add('is-dragging');
          try {
            evt.dataTransfer.effectAllowed = 'move';
            evt.dataTransfer.setData('text/plain', li.getAttribute('data-value') || '');
          } catch (error) {
            // ignore
          }
        });
        li.addEventListener('dragend', function(){
          li.classList.remove('is-dragging');
          update();
        });
        var removeButton = qs(li, '.your-share-network-remove');
        if (removeButton){
          removeButton.addEventListener('click', handleRemove);
        }
      }

      function createItem(slug, label, color, removeLabel){
        var li = document.createElement('li');
        li.className = 'your-share-network-item';
        li.setAttribute('data-value', slug);
        li.setAttribute('draggable', 'true');

        var handle = document.createElement('span');
        handle.className = 'your-share-network-handle';
        handle.setAttribute('aria-hidden', 'true');
        handle.textContent = '⋮⋮';

        var swatch = document.createElement('span');
        swatch.className = 'your-share-network-swatch';
        if (color){
          swatch.style.setProperty('--your-share-network-color', color);
        }

        var text = document.createElement('span');
        text.className = 'your-share-network-label';
        text.textContent = label;

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'your-share-network-remove';
        remove.setAttribute('data-action', 'remove');
        remove.setAttribute('aria-label', removeLabel || ('Remove ' + label));
        remove.textContent = '×';

        li.appendChild(handle);
        li.appendChild(swatch);
        li.appendChild(text);
        li.appendChild(remove);

        attach(li);
        return li;
      }

      function dragOver(event){
        event.preventDefault();
        var dragging = list.querySelector('.your-share-network-item.is-dragging');
        if (!dragging){
          return;
        }
        var after = getDragAfterElement(list, event.clientY);
        if (!after){
          list.appendChild(dragging);
        } else {
          list.insertBefore(dragging, after);
        }
      }

      function getDragAfterElement(container, y){
        var elements = qsa(container, '.your-share-network-item:not(.is-dragging)');
        var closest = { offset: Number.NEGATIVE_INFINITY, element: null };

        elements.forEach(function(child){
          var box = child.getBoundingClientRect();
          var offset = y - box.top - box.height / 2;
          if (offset < 0 && offset > closest.offset){
            closest = { offset: offset, element: child };
          }
        });

        return closest.element;
      }

      list.addEventListener('dragover', dragOver);

      buttons.forEach(function(button){
        button.addEventListener('click', function(){
          if (button.disabled){
            return;
          }
          var slug = button.getAttribute('data-value');
          var label = button.getAttribute('data-label') || slug;
          var color = button.getAttribute('data-color') || '';
          var removeLabel = button.getAttribute('data-remove-label') || ('Remove ' + label);
          var item = createItem(slug, label, color, removeLabel);
          list.appendChild(item);
          button.disabled = true;
          button.classList.add('is-active');
          update();
        });
      });

      qsa(list, '.your-share-network-item').forEach(attach);
      update();
    });
  }

  function setupShortcodePreview(root){
    var inputs = qsa(root, '[data-your-share-shortcode-prop]');
    inputs.forEach(function(input){
      input.addEventListener('change', function(){ refreshShortcodePreview(root); });
      input.addEventListener('input', function(){ refreshShortcodePreview(root); });
    });
    refreshShortcodePreview(root);
  }

  function setupFollowShortcodePreview(root){
    var inputs = qsa(root, '[data-your-share-follow-prop]');
    inputs.forEach(function(input){
      input.addEventListener('change', function(){ refreshFollowShortcodePreview(root); });
      input.addEventListener('input', function(){ refreshFollowShortcodePreview(root); });
    });
    refreshFollowShortcodePreview(root);
  }

  function setupUtmPreview(root){
    if (!qs(root, '[data-your-share-utm-preview]')){
      return;
    }
    var inputs = qsa(root, '[data-your-share-utm-prop]');

    function refresh(){
      refreshUtmPreview(root);
    }

    inputs.forEach(function(input){
      input.addEventListener('change', refresh);
      input.addEventListener('input', refresh);
    });
    refresh();
  }

  function refreshShortcodePreview(root){
    var target = qs(root, '[data-your-share-shortcode]');
    if (!target){
      return;
    }
    var networksInput = qs(root, '[data-your-share-network-input]');
    var styleSelect = qs(root, '[data-your-share-shortcode-prop="style"]');
    var sizeSelect = qs(root, '[data-your-share-shortcode-prop="size"]');
    var labelsSelect = qs(root, '[data-your-share-shortcode-prop="labels"]');
    var alignSelect = qs(root, '[data-your-share-shortcode-prop="align"]');
    var brandToggle = qs(root, '[data-your-share-shortcode-prop="brand"]');

    var networks = networksInput ? networksInput.value.trim() : '';
    var style = styleSelect ? styleSelect.value : 'solid';
    var size = sizeSelect ? sizeSelect.value : 'md';
    var labels = labelsSelect ? labelsSelect.value : 'auto';
    var align = alignSelect ? alignSelect.value : 'left';
    var brand = '0';
    if (brandToggle){
      if (brandToggle.type === 'checkbox'){
        brand = brandToggle.checked ? '1' : '0';
      } else {
        brand = brandToggle.value || '0';
      }
    }

    var shortcode = '[your_share';
    shortcode += ' networks="' + networks + '"';
    shortcode += ' style="' + style + '"';
    shortcode += ' size="' + size + '"';
    shortcode += ' labels="' + labels + '"';
    shortcode += ' align="' + align + '"';
    shortcode += ' brand="' + brand + '"';
    shortcode += ']';

    target.textContent = shortcode;
  }

  function refreshFollowShortcodePreview(root){
    var container = qs(root, '[data-your-share-follow-shortcode]');
    if (!container){
      return;
    }

    var output = qs(container, '[data-your-share-follow-output]');
    if (!output){
      return;
    }

    var defaults = {
      networks: container.getAttribute('data-default-networks') || '',
      style: container.getAttribute('data-default-style') || 'solid',
      size: container.getAttribute('data-default-size') || 'md',
      align: container.getAttribute('data-default-align') || 'left',
      brand: container.getAttribute('data-default-brand') || '1',
      labels: container.getAttribute('data-default-labels') || 'show'
    };

    var networksInput = qs(root, '[data-your-share-follow-networks]');
    var styleInput = qs(root, '[data-your-share-follow-prop="style"]');
    var sizeInput = qs(root, '[data-your-share-follow-prop="size"]');
    var alignInput = qs(root, '[data-your-share-follow-prop="align"]');
    var brandInput = qs(root, '[data-your-share-follow-prop="brand"]');
    var labelsInput = qs(root, '[data-your-share-follow-prop="labels"]');

    var networks = networksInput && networksInput.value ? networksInput.value.trim() : defaults.networks;
    var style = styleInput ? styleInput.value : defaults.style;
    var size = sizeInput ? sizeInput.value : defaults.size;
    var align = alignInput ? alignInput.value : defaults.align;
    var brand = defaults.brand;
    if (brandInput){
      if (brandInput.type === 'checkbox'){
        brand = brandInput.checked ? '1' : '0';
      } else {
        brand = brandInput.value || defaults.brand;
      }
    }
    var labels = labelsInput ? labelsInput.value : defaults.labels;

    var shortcode = '[share_follow';
    if (networks){
      shortcode += ' networks="' + networks + '"';
    }
    shortcode += ' style="' + style + '"';
    shortcode += ' size="' + size + '"';
    shortcode += ' align="' + align + '"';
    shortcode += ' brand="' + brand + '"';
    shortcode += ' labels="' + labels + '"';
    shortcode += ']';

    output.textContent = shortcode;
  }

  function refreshUtmPreview(root){
    var container = qs(root, '[data-your-share-utm-preview]');
    if (!container){
      return;
    }
    var output = qs(container, '[data-your-share-utm-output]');
    if (!output){
      return;
    }

    var base = container.getAttribute('data-base') || window.location.href;
    var network = container.getAttribute('data-network') || 'x';
    var enabled = qs(root, '[data-your-share-utm-prop="enabled"]');
    var medium = qs(root, '[data-your-share-utm-prop="medium"]');
    var campaign = qs(root, '[data-your-share-utm-prop="campaign"]');
    var term = qs(root, '[data-your-share-utm-prop="term"]');
    var content = qs(root, '[data-your-share-utm-prop="content"]');

    var url;
    try {
      url = new URL(base);
    } catch (error) {
      output.textContent = base;
      return;
    }

    if (!enabled || enabled.checked){
      var params = new URLSearchParams();
      params.set('utm_source', network);
      if (medium && medium.value){
        params.set('utm_medium', medium.value);
      }
      if (campaign && campaign.value){
        params.set('utm_campaign', campaign.value);
      }
      if (term && term.value){
        params.set('utm_term', term.value);
      }
      if (content && content.value){
        params.set('utm_content', content.value);
      }
      url.search = params.toString();
    } else {
      url.search = '';
    }

    output.textContent = url.toString();
  }

  document.addEventListener('DOMContentLoaded', init);
})();
