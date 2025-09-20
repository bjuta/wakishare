(function(){
  function qs(root, selector){
    return root ? root.querySelector(selector) : null;
  }

  function qsa(root, selector){
    return root ? Array.prototype.slice.call(root.querySelectorAll(selector)) : [];
  }

  function init(){
    var root = document.querySelector('[data-your-share-admin]');
    if (!root){
      return;
    }

    setupTabs(root);
    setupNetworkPicker(root);
    setupShortcodePreview(root);
    setupFollowShortcodePreview(root);
    setupUtmPreview(root);
  }

  function setupTabs(root){
    var tabs = qsa(root, '[data-your-share-tab]');
    var panels = qsa(root, '[data-your-share-panel]');
    var currentInput = qs(root, '[data-your-share-current-tab]');
    if (!tabs.length || !panels.length || !currentInput){
      return;
    }

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
    var brandToggle = qs(root, '[data-your-share-shortcode-prop="brand"]');

    var networks = networksInput ? networksInput.value.trim() : '';
    var style = styleSelect ? styleSelect.value : 'solid';
    var size = sizeSelect ? sizeSelect.value : 'md';
    var labels = labelsSelect ? labelsSelect.value : 'auto';
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
