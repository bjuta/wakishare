(function(){
  var messages = window.yourShareMessages || {};
  var mediaConfig = window.yourShareMedia || {};

  var overlaySelectors = Array.isArray(mediaConfig.selectors) ? mediaConfig.selectors : [];
  overlaySelectors = overlaySelectors.map(function(selector){
    return (selector || '').trim();
  }).filter(function(selector){
    return selector.length > 0;
  });

  var overlayTemplateHtml = (mediaConfig.markup || '').trim();
  var overlaySelectorString = overlaySelectors.join(',');
  var overlayPosition = (mediaConfig.position || 'top-end').toLowerCase();
  var overlayTrigger = (mediaConfig.trigger || 'hover').toLowerCase();
  var overlayToggleLabel = mediaConfig.toggleLabel || messages.mediaToggle || 'Share this media';
  var overlayToggleText = mediaConfig.toggleText || messages.shareLabel || 'Share';
  var overlayStates = [];
  var overlayObserver = null;
  var overlayIdCounter = 0;

  var allowedPositions = ['top-start', 'top-end', 'bottom-start', 'bottom-end', 'center'];
  if (allowedPositions.indexOf(overlayPosition) === -1){
    overlayPosition = 'top-end';
  }

  var allowedTriggers = ['hover', 'always'];
  if (allowedTriggers.indexOf(overlayTrigger) === -1){
    overlayTrigger = 'hover';
  }

  function overlayEnabled(){
    return overlaySelectors.length > 0 && overlayTemplateHtml.length > 0;
  }

  function cloneOverlayShare(){
    if (!overlayTemplateHtml){
      return null;
    }
    var template = document.createElement('template');
    template.innerHTML = overlayTemplateHtml;
    var first = template.content.firstElementChild;
    if (!first){
      return null;
    }
    var clone = first.cloneNode(true);
    clone.classList.add('waki-share-overlay-share');
    return clone;
  }

  function getFocusableElements(container){
    return Array.prototype.filter.call(container.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'), function(el){
      if (el.hasAttribute('disabled')){
        return false;
      }
      var style = window.getComputedStyle(el);
      return style.display !== 'none' && style.visibility !== 'hidden';
    });
  }

  function trapFocus(event, state){
    var focusable = getFocusableElements(state.overlay);
    if (!focusable.length){
      event.preventDefault();
      return;
    }

    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    var active = document.activeElement;

    if (event.shiftKey){
      if (active === first || !state.overlay.contains(active)){
        event.preventDefault();
        last.focus();
      }
    } else if (active === last){
      event.preventDefault();
      first.focus();
    }
  }

  function openOverlay(state, focusMenu){
    if (state.open){
      return;
    }
    state.open = true;
    state.wrapper.classList.add('is-open');
    state.overlay.classList.add('is-open');
    state.menu.setAttribute('aria-hidden', 'false');
    state.toggle.setAttribute('aria-expanded', 'true');

    if (focusMenu){
      var focusable = getFocusableElements(state.overlay);
      for (var i = 0; i < focusable.length; i++){
        if (focusable[i] !== state.toggle){
          focusable[i].focus({ preventScroll: true });
          break;
        }
      }
    }
  }

  function closeOverlay(state){
    if (!state.open || overlayTrigger === 'always'){
      return;
    }
    state.open = false;
    state.wrapper.classList.remove('is-open');
    state.overlay.classList.remove('is-open');
    state.menu.setAttribute('aria-hidden', 'true');
    state.toggle.setAttribute('aria-expanded', 'false');
  }

  function closeAllOverlays(except){
    for (var i = 0; i < overlayStates.length; i++){
      if (overlayStates[i] !== except){
        closeOverlay(overlayStates[i]);
      }
    }
  }

  function bindOverlayEvents(state){
    if (overlayTrigger === 'always'){
      return;
    }

    var closeTimer = null;

    function scheduleClose(delay){
      if (overlayTrigger === 'always'){
        return;
      }
      clearTimeout(closeTimer);
      closeTimer = setTimeout(function(){
        if (!state.wrapper.contains(document.activeElement)){
          closeOverlay(state);
        }
      }, delay);
    }

    state.wrapper.addEventListener('mouseenter', function(){
      if (overlayTrigger !== 'hover'){
        return;
      }
      clearTimeout(closeTimer);
      closeAllOverlays(state);
      openOverlay(state, false);
    });

    state.wrapper.addEventListener('mouseleave', function(){
      if (overlayTrigger !== 'hover'){
        return;
      }
      scheduleClose(120);
    });

    state.wrapper.addEventListener('focusin', function(){
      closeAllOverlays(state);
      openOverlay(state, false);
    });

    state.wrapper.addEventListener('focusout', function(event){
      if (state.wrapper.contains(event.relatedTarget)){
        return;
      }
      if (overlayTrigger === 'hover'){
        scheduleClose(100);
      } else {
        closeOverlay(state);
      }
    });

    state.toggle.addEventListener('click', function(event){
      event.preventDefault();
      if (state.open){
        closeOverlay(state);
      } else {
        closeAllOverlays(state);
        openOverlay(state, true);
      }
    });

    state.overlay.addEventListener('keydown', function(event){
      if (event.key === 'Escape' || event.key === 'Esc'){
        if (state.open && overlayTrigger !== 'always'){
          event.preventDefault();
          closeOverlay(state);
          state.toggle.focus({ preventScroll: true });
        }
        return;
      }

      if (event.key === 'Tab'){
        if (!state.open && overlayTrigger !== 'always'){
          return;
        }
        trapFocus(event, state);
      }
    });
  }

  function isMediaCandidate(el){
    if (!el || !el.tagName){
      return false;
    }
    var tag = el.tagName.toLowerCase();
    if (tag === 'img' || tag === 'video'){
      return true;
    }
    if (tag === 'iframe'){
      var src = (el.getAttribute('src') || el.getAttribute('data-src') || '').toLowerCase();
      return /youtube\.com|youtu\.be|youtube-nocookie\.com|player\.vimeo\.com/.test(src);
    }
    return false;
  }

  function findMediaElement(node){
    if (isMediaCandidate(node)){
      return node;
    }
    var nested = node.querySelectorAll('img, video, iframe');
    for (var i = 0; i < nested.length; i++){
      if (isMediaCandidate(nested[i])){
        return nested[i];
      }
    }
    return null;
  }

  function promoteTarget(el){
    var target = el;
    if (!target){
      return target;
    }

    var link = target.closest('a');
    if (link && link.querySelectorAll('img, video, iframe').length === 1){
      target = link;
    }

    return target;
  }

  function createOverlay(target){
    if (!target || target.nodeType !== 1){
      return;
    }
    if (target.closest('.waki-share-media') || target.getAttribute('data-your-share-overlay-target') === '1'){
      return;
    }

    var parent = target.parentNode;
    if (!parent){
      return;
    }

    var wrapper = document.createElement('span');
    wrapper.className = 'waki-share-media';
    wrapper.setAttribute('data-trigger', overlayTrigger);
    wrapper.setAttribute('data-position', overlayPosition);

    var computed = window.getComputedStyle(target);
    var display = (computed.display || '').toLowerCase();
    var blockDisplays = ['block', 'flex', 'grid', 'table', 'list-item'];
    if (blockDisplays.indexOf(display) !== -1){
      wrapper.dataset.display = 'block';
      wrapper.style.display = 'block';
    } else {
      wrapper.dataset.display = 'inline';
      wrapper.style.display = 'inline-block';
    }

    parent.insertBefore(wrapper, target);
    wrapper.appendChild(target);
    wrapper.dataset.yourShareOverlay = '1';
    target.setAttribute('data-your-share-overlay-target', '1');

    var overlay = document.createElement('div');
    overlay.className = 'waki-share-overlay';
    overlay.setAttribute('data-position', overlayPosition);

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'waki-share-overlay__toggle';
    toggle.setAttribute('aria-haspopup', 'true');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', overlayToggleLabel);
    toggle.textContent = overlayToggleText;

    var menu = document.createElement('div');
    menu.className = 'waki-share-overlay__menu';
    menu.setAttribute('aria-hidden', 'true');
    menu.setAttribute('role', 'menu');
    menu.setAttribute('tabindex', '-1');

    var shareMarkup = cloneOverlayShare();
    if (!shareMarkup){
      wrapper.parentNode.insertBefore(target, wrapper);
      wrapper.remove();
      return;
    }

    menu.appendChild(shareMarkup);
    overlay.appendChild(toggle);
    overlay.appendChild(menu);
    wrapper.appendChild(overlay);

    overlayIdCounter += 1;
    var menuId = 'yourShareOverlayMenu' + overlayIdCounter;
    menu.id = menuId;
    toggle.setAttribute('aria-controls', menuId);

    var state = {
      wrapper: wrapper,
      target: target,
      overlay: overlay,
      toggle: toggle,
      menu: menu,
      open: false
    };

    overlayStates.push(state);

    if (overlayTrigger === 'always'){
      state.open = true;
      wrapper.classList.add('is-open');
      overlay.classList.add('is-open');
      menu.setAttribute('aria-hidden', 'false');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-hidden', 'true');
      toggle.setAttribute('tabindex', '-1');
    } else {
      bindOverlayEvents(state);
    }
  }

  function maybeCreateOverlay(node){
    if (!(node instanceof Element)){
      return;
    }
    if (!overlaySelectorString || node.closest('.waki-share-media')){
      return;
    }

    if (node.matches && node.matches(overlaySelectorString)){
      var candidate = findMediaElement(node);
      if (candidate){
        createOverlay(promoteTarget(candidate));
      }
    }

    var matches = node.querySelectorAll(overlaySelectorString);
    Array.prototype.forEach.call(matches, function(match){
      var media = findMediaElement(match);
      if (media){
        createOverlay(promoteTarget(media));
      }
    });
  }

  function initMediaOverlays(){
    if (!overlayEnabled()){
      return;
    }

    if (document.body){
      maybeCreateOverlay(document.body);
    }

    if (typeof MutationObserver === 'undefined' || !document.body){
      return;
    }

    overlayObserver = new MutationObserver(function(mutations){
      mutations.forEach(function(mutation){
        Array.prototype.forEach.call(mutation.addedNodes, function(node){
          if (node instanceof Element){
            maybeCreateOverlay(node);
          }
        });
      });
    });

    overlayObserver.observe(document.body, { childList: true, subtree: true });
  }

  function openPopup(url){
    var w = 600;
    var h = 500;
    var left = (screen.width - w) / 2;
    var top = (screen.height - h) / 2;
    window.open(url, 'yourShare', 'toolbar=0,status=0,width=' + w + ',height=' + h + ',top=' + top + ',left=' + left);
  }

  function parseCountryFromLocale(locale){
    if (!locale){
      return '';
    }

    var token = (locale.split(';')[0] || '').trim();
    if (!token){
      return '';
    }

    token = token.replace('_', '-');
    var parts = token.split('-');
    if (parts.length < 2){
      return '';
    }

    var country = (parts[1] || '').trim().slice(0, 2).toUpperCase();

    if (!country || country.length !== 2 || !/^[A-Z]{2}$/.test(country)){
      return '';
    }

    return country;
  }

  function hydrateGeo(){
    var geo = window.yourShareGeo || {};
    var wrappers = document.querySelectorAll('.waki-share');
    var markupCountry = '';
    var markupSource = '';

    Array.prototype.forEach.call(wrappers, function(el){
      if (markupCountry){
        return;
      }
      var country = el.getAttribute('data-your-share-country');
      if (country){
        markupCountry = country.toUpperCase();
        markupSource = el.getAttribute('data-your-share-country-source') || '';
      }
    });

    if (!geo.country && markupCountry){
      geo.country = markupCountry;
      geo.source = markupSource || 'server';
    }

    if (!geo.country){
      var guess = '';
      if (Array.isArray(navigator.languages)){
        for (var i = 0; i < navigator.languages.length; i++){
          guess = parseCountryFromLocale(navigator.languages[i]);
          if (guess){
            break;
          }
        }
      }
      if (!guess && navigator.language){
        guess = parseCountryFromLocale(navigator.language);
      }

      if (guess){
        geo.country = guess;
        geo.source = 'language';
      }
    }

    if (geo.country){
      Array.prototype.forEach.call(wrappers, function(el){
        if (!el.getAttribute('data-your-share-country')){
          el.setAttribute('data-your-share-country', geo.country);
        }
        if (!el.getAttribute('data-your-share-country-source')){
          el.setAttribute('data-your-share-country-source', geo.source || 'language');
        }
      });
    }

    window.yourShareGeo = geo;
  }

  function toast(msg){
    var text = msg || messages.copySuccess || 'Link copied';
    var toastEl = document.getElementById('wakiShareToast');

    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.id = 'wakiShareToast';
      document.body.appendChild(toastEl);
    }

    toastEl.textContent = text;
    toastEl.classList.add('show');
    setTimeout(function(){ toastEl.classList.remove('show'); }, 1600);
  }

  function handleCopy(){
    var current = window.location.href;
    try {
      navigator.clipboard.writeText(current);
      toast(messages.copySuccess);
    } catch (err) {
      var ta = document.createElement('textarea');
      ta.value = current;
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand('copy');
        toast(messages.copySuccess);
      } finally {
        document.body.removeChild(ta);
      }
    }
  }

  function updateFloatingVisibility(){
    var bars = document.querySelectorAll('.waki-share-floating');
    if (!bars.length) {
      return;
    }

    var viewport = window.innerWidth || document.documentElement.clientWidth;

    Array.prototype.forEach.call(bars, function(bar){
      var style = window.getComputedStyle(bar);
      var breakpoint = parseInt(style.getPropertyValue('--waki-breakpoint') || '1024', 10);
      if (viewport < breakpoint) {
        bar.style.display = 'none';
      } else {
        bar.style.display = '';
      }
    });
  }

  document.addEventListener('click', function(event){
    if (overlayStates.length){
      overlayStates.forEach(function(state){
        if (state.open && overlayTrigger !== 'always' && !state.wrapper.contains(event.target)){
          closeOverlay(state);
        }
      });
    }

    var button = event.target.closest('.waki-btn');
    if (!button || !button.closest('.waki-share')) {
      return;
    }

    if (button.closest('.waki-share-overlay')){
      closeAllOverlays(null);
    }

    var network = button.getAttribute('data-net');

    if (network === 'copy') {
      event.preventDefault();
      handleCopy();
      return;
    }

    if (network === 'native') {
      event.preventDefault();
      if (navigator.share) {
        navigator.share({ title: document.title, url: window.location.href }).catch(function(){});
      } else {
        toast(messages.shareUnsupported || 'Sharing not supported');
      }
      return;
    }

    var href = button.getAttribute('href') || '';
    if (href.indexOf('mailto:') === 0 || href.indexOf('wa.me') !== -1) {
      return;
    }

    event.preventDefault();
    openPopup(href);
  });

  window.addEventListener('resize', updateFloatingVisibility);

  function onReady(){
    hydrateGeo();
    updateFloatingVisibility();
    initMediaOverlays();
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', onReady);
  } else {
    onReady();
  }
})();
