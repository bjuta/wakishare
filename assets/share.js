(function(){
  var messages = window.yourShareMessages || {};
  var reactionsConfig = window.yourShareReactions || {};
  var analyticsConfig = window.yourShareAnalytics || {};
  var reactionStore = null;
  var mediaConfig = window.yourShareMedia || {};
  var sheetState = null;
  var openPaletteState = null;
  var interactionGranted = false;
  var deferredSdkLoaders = [];
  var sheetConfettiKey = 'yourShareSheetConfetti';
  var sheetCelebrated = false;
  var shareSheetBreakpointFallback = 768;

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

  function runDeferredSdkLoaders(){
    while (deferredSdkLoaders.length){
      var loader = deferredSdkLoaders.shift();
      if (typeof loader === 'function'){
        try {
          loader();
        } catch (error) {
          // ignore loader errors
        }
      }
    }
  }

  function deferSdkLoader(fn){
    if (typeof fn !== 'function'){
      return;
    }
    if (interactionGranted){
      try {
        fn();
      } catch (error) {
        // ignore loader errors
      }
      return;
    }
    deferredSdkLoaders.push(fn);
  }

  function grantInteractionConsent(){
    if (interactionGranted){
      return;
    }
    interactionGranted = true;
    runDeferredSdkLoaders();
    if (typeof window.CustomEvent === 'function'){
      try {
        document.dispatchEvent(new CustomEvent('yourShareConsentGranted'));
      } catch (error) {
        // ignore custom event issues
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
  var countsConfig = window.yourShareCountsConfig || {};
  var refreshTimers = typeof WeakMap !== 'undefined' ? new WeakMap() : null;

  function formatCount(value){
    var num = parseInt(value, 10);
    if (!isFinite(num) || num < 0){
      num = 0;
    }

    if (num >= 1000000){
      var millions = (num / 1000000).toFixed(1);
      if (millions.slice(-2) === '.0'){
        millions = millions.slice(0, -2);
      }
      return millions + 'M';
    }

    if (num >= 1000){
      var thousands = (num / 1000).toFixed(1);
      if (thousands.slice(-2) === '.0'){
        thousands = thousands.slice(0, -2);
      }
      return thousands + 'K';
    }

    if (typeof num.toLocaleString === 'function'){
      return num.toLocaleString();
    }

    return String(num);
  }

  function applyCounts(wrapper, payload){
    if (!payload || typeof payload !== 'object'){
      return;
    }

    var networks = payload.networks || {};
    Object.keys(networks).forEach(function(key){
      var networkData = networks[key] || {};
      var total = parseInt(networkData.total, 10);
      if (!isFinite(total) || total < 0){
        total = 0;
      }

      var button = wrapper.querySelector('.waki-btn[data-net="' + key + '"]');
      if (!button){
        return;
      }

      var badge = button.querySelector('[data-your-share-count]');
      if (!badge){
        return;
      }

      var visible = total > 0;
      badge.textContent = formatCount(total);
      badge.setAttribute('data-value', total);
      badge.setAttribute('data-visible', visible ? '1' : '0');
      badge.setAttribute('aria-hidden', visible ? 'false' : 'true');
    });

    var totalValue = wrapper.querySelector('[data-your-share-total-value]');
    if (totalValue){
      var totalCount = parseInt(payload.total, 10);
      if (!isFinite(totalCount) || totalCount < 0){
        totalCount = 0;
      }
      totalValue.textContent = formatCount(totalCount);
      totalValue.setAttribute('data-value', totalCount);
    }
  }

  function buildCountsUrl(wrapper, force){
    if (!countsConfig.restUrl){
      return '';
    }

    var base = countsConfig.restUrl.replace(/\/$/, '');
    var postId = wrapper.getAttribute('data-your-share-post');
    if (postId === null){
      return '';
    }

    var url = base + '/' + encodeURIComponent(postId || '0');
    var params = [];
    var networks = wrapper.getAttribute('data-your-share-networks') || '';
    if (networks){
      params.push('networks=' + encodeURIComponent(networks));
    }
    var shareUrl = wrapper.getAttribute('data-your-share-count-url') || '';
    if (shareUrl){
      params.push('shareUrl=' + encodeURIComponent(shareUrl));
    }
    if (force){
      params.push('force=1');
    }

    if (params.length){
      url += '?' + params.join('&');
    }

    return url;
  }

  function scheduleRefresh(wrapper){
    if (!refreshTimers){
      return;
    }

    var interval = parseInt(countsConfig.refreshInterval, 10);
    if (!isFinite(interval) || interval <= 0){
      return;
    }

    if (refreshTimers.has(wrapper)){
      clearTimeout(refreshTimers.get(wrapper));
    }

    var timer = setTimeout(function(){
      fetchCounts(wrapper, true);
    }, interval * 60 * 1000);

    refreshTimers.set(wrapper, timer);
  }

  function fetchCounts(wrapper, force){
    if (!countsConfig || !countsConfig.enabled){
      return;
    }

    if (!wrapper || wrapper.getAttribute('data-your-share-counts') !== '1'){
      return;
    }

    var url = buildCountsUrl(wrapper, force);
    if (!url){
      return;
    }

    var options = { credentials: 'same-origin' };
    if (countsConfig.nonce){
      options.headers = { 'X-WP-Nonce': countsConfig.nonce };
    }

    fetch(url, options)
      .then(function(response){
        if (!response.ok){
          throw new Error('Request failed');
        }
        return response.json();
      })
      .then(function(data){
        applyCounts(wrapper, data);
        scheduleRefresh(wrapper);
      })
      .catch(function(){});
  }

  function hydrateCounts(){
    if (!countsConfig || !countsConfig.enabled){
      return;
    }

    var wrappers = document.querySelectorAll('.waki-share[data-your-share-counts="1"]');
    Array.prototype.forEach.call(wrappers, function(wrapper){
      fetchCounts(wrapper, false);
    });
  }

  function refreshCounts(target, force){
    var elements = [];
    if (!target){
      elements = document.querySelectorAll('.waki-share[data-your-share-counts="1"]');
    } else if (typeof target === 'string'){
      elements = document.querySelectorAll(target);
    } else if (target instanceof Element){
      elements = [target];
    } else if (typeof target.length === 'number'){
      elements = target;
    }

    Array.prototype.forEach.call(elements, function(wrapper){
      if (wrapper && wrapper.getAttribute && wrapper.getAttribute('data-your-share-counts') === '1'){
        fetchCounts(wrapper, !!force);
      }
    });
  }

  window.yourShareCounts = window.yourShareCounts || {};
  window.yourShareCounts.refresh = function(target, force){
    refreshCounts(target, force);
  };

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

  function handleCopy(url, snippet){
    var parts = [];
    if (snippet){
      parts.push(snippet);
    }
    parts.push(url || window.location.href);
    var message = parts.join('\n\n');

    function legacyCopy(){
      var ta = document.createElement('textarea');
      ta.value = message;
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand('copy');
        toast(messages.copySuccess);
      } finally {
        document.body.removeChild(ta);
      }
    }

    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function'){
      navigator.clipboard.writeText(message).then(function(){
        toast(messages.copySuccess);
      }).catch(legacyCopy);
      return;
    }

    legacyCopy();
  }

  function analyticsRestRoot(){
    if (!analyticsConfig || !analyticsConfig.rest || !analyticsConfig.rest.root){
      return '';
    }
    var root = String(analyticsConfig.rest.root);
    if (!root){
      return '';
    }
    if (root.charAt(root.length - 1) === '/'){
      root = root.slice(0, -1);
    }
    return root;
  }

  function analyticsHeaders(){
    var headers = {};
    if (analyticsConfig && analyticsConfig.rest && analyticsConfig.rest.nonce){
      headers['X-WP-Nonce'] = analyticsConfig.rest.nonce;
    }
    return headers;
  }

  function pushDataLayer(eventType, payload){
    try {
      if (!window.dataLayer || typeof window.dataLayer.push !== 'function'){
        return;
      }
      window.dataLayer.push({
        event: 'your_share_interaction',
        interaction_type: eventType,
        post_id: payload.post_id,
        network: payload.network,
        placement: payload.placement,
        share_url: payload.url
      });
    } catch (error) {
      // ignore dataLayer errors
    }
  }

  function pushGa4(eventType, payload){
    if (!analyticsConfig || !analyticsConfig.ga4){
      return;
    }
    try {
      if (typeof window.gtag === 'function'){
        window.gtag('event', 'your_share_interaction', {
          interaction_type: eventType,
          network: payload.network,
          post_id: payload.post_id,
          placement: payload.placement,
          share_url: payload.url
        });
      }
    } catch (error) {
      // ignore GA errors
    }
  }

  function sendAnalyticsEvent(eventType, details){
    if (!details){
      details = {};
    }

    var payload = {
      post_id: typeof details.postId === 'number' ? details.postId : parseInt(details.postId || '0', 10),
      network: details.network || '',
      placement: details.placement || '',
      url: details.url || ''
    };

    if (!isFinite(payload.post_id)){
      payload.post_id = 0;
    }

    var consoleEnabled = !!(analyticsConfig && analyticsConfig.console);
    var storeEnabled = !!(analyticsConfig && analyticsConfig.store);
    var ga4Enabled = !!(analyticsConfig && analyticsConfig.ga4);

    if (consoleEnabled){
      try {
        console.info('[Your Share]', eventType, payload);
      } catch (error) {
        // ignore logging errors
      }
    }

    if (storeEnabled || ga4Enabled){
      pushDataLayer(eventType, payload);
    }
    if (ga4Enabled){
      pushGa4(eventType, payload);
    }

    if (!storeEnabled){
      return;
    }

    if (typeof window.fetch !== 'function'){
      return;
    }

    var root = analyticsRestRoot();
    if (!root){
      return;
    }

    var headers = analyticsHeaders();
    headers['Content-Type'] = 'application/json';

    try {
      window.fetch(root + '/event', {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers,
        body: JSON.stringify({
          event: eventType,
          post_id: payload.post_id,
          network: payload.network,
          placement: payload.placement,
          url: payload.url
        })
      }).catch(function(){
        // swallow network errors
      });
    } catch (error) {
      // ignore fetch issues
    }
  }

  function getSharePostId(wrapper){
    if (!wrapper){
      return 0;
    }
    var raw = wrapper.getAttribute('data-your-share-post-id') || wrapper.getAttribute('data-your-share-post') || '0';
    var parsed = parseInt(raw, 10);
    if (!isFinite(parsed)){
      return 0;
    }
    return parsed;
  }

  function trackShare(wrapper, network, url){
    var placement = '';
    if (wrapper){
      placement = wrapper.getAttribute('data-your-share-placement') || '';
    }
    sendAnalyticsEvent('share', {
      postId: getSharePostId(wrapper),
      network: network,
      placement: placement,
      url: url || ''
    });
  }

  function getShareContext(wrapper){
    var context = {
      title: document.title || '',
      url: window.location.href,
      snippet: '',
      image: ''
    };

    if (!wrapper || !wrapper.dataset){
      return context;
    }

    var data = wrapper.dataset;

    if (data.yourShareTitle){
      context.title = data.yourShareTitle;
    }
    if (data.yourShareUrl){
      context.url = data.yourShareUrl;
    }
    if (data.yourShareSnippet){
      context.snippet = data.yourShareSnippet;
    }
    if (data.yourShareImage){
      context.image = data.yourShareImage;
    }

    return context;
  }

  function trackReaction(bar, postId, slug){
    var placement = '';
    if (bar){
      placement = bar.getAttribute('data-placement') || '';
    }
    sendAnalyticsEvent('reaction', {
      postId: postId,
      network: slug,
      placement: placement
    });
  }

  function getThrottleConfig(){
    var throttle = reactionsConfig && reactionsConfig.throttle ? reactionsConfig.throttle : {};
    var ttl = typeof throttle.cookieTtl === 'number' ? throttle.cookieTtl : 31536000;
    return {
      cookiePrefix: throttle.cookiePrefix || 'yourshare_reacted_',
      storageKey: throttle.storageKey || 'yourShareReactions',
      cookieTtl: ttl > 0 ? ttl : 31536000
    };
  }

  function readCookie(name){
    if (!document.cookie){
      return '';
    }
    var parts = document.cookie.split(';');
    for (var i = 0; i < parts.length; i++){
      var part = parts[i].trim();
      if (part.indexOf(name + '=') === 0){
        return decodeURIComponent(part.substring(name.length + 1));
      }
    }
    return '';
  }

  function writeCookie(name, value, ttl){
    var expires = '';
    if (typeof ttl === 'number'){
      var date = new Date();
      if (ttl <= 0){
        date.setTime(0);
      } else {
        date.setTime(date.getTime() + (ttl * 1000));
      }
      expires = '; expires=' + date.toUTCString();
    }
    document.cookie = name + '=' + encodeURIComponent(value || '') + expires + '; path=/; SameSite=Lax';
  }

  function normalizeReactions(value){
    if (!value){
      return [];
    }
    var list = [];
    if (Array.isArray(value)){
      list = value.slice();
    } else if (typeof value === 'string'){
      var trimmed = value.trim();
      if (!trimmed){
        list = [];
      } else if (trimmed.charAt(0) === '[' || trimmed.charAt(0) === '{'){
        try {
          var parsed = JSON.parse(trimmed);
          if (Array.isArray(parsed)){
            list = parsed;
          } else if (parsed && typeof parsed === 'object'){
            list = Object.keys(parsed).filter(function(key){ return !!parsed[key]; });
          } else {
            list = trimmed.split(',');
          }
        } catch (error) {
          list = trimmed.split(',');
        }
      } else {
        list = trimmed.split(',');
      }
    } else if (typeof value === 'object'){
      Object.keys(value).forEach(function(key){
        if (value[key]){
          list.push(key);
        }
      });
    }

    var seen = {};
    return list.map(function(item){
      var slug = String(item || '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
      return slug;
    }).filter(function(slug){
      if (!slug){
        return false;
      }
      if (seen[slug]){
        return false;
      }
      seen[slug] = true;
      return true;
    });
  }

  function readReactionStore(){
    if (reactionStore !== null){
      return reactionStore;
    }
    var store = {};
    var config = getThrottleConfig();
    try {
      if (window.localStorage){
        var raw = window.localStorage.getItem(config.storageKey);
        if (raw){
          var parsed = JSON.parse(raw);
          if (parsed && typeof parsed === 'object'){
            Object.keys(parsed).forEach(function(key){
              store[key] = normalizeReactions(parsed[key]);
            });
          }
        }
      }
    } catch (error) {
      // ignore storage errors
    }
    reactionStore = store;
    return store;
  }

  function writeReactionStore(store){
    var output = {};
    Object.keys(store || {}).forEach(function(key){
      var normalized = normalizeReactions(store[key]);
      if (normalized.length){
        output[key] = normalized;
      }
    });
    reactionStore = output;
    var config = getThrottleConfig();
    try {
      if (window.localStorage){
        window.localStorage.setItem(config.storageKey, JSON.stringify(output));
      }
    } catch (error) {
      // ignore storage errors
    }
  }

  function getStoredReactions(postId){
    if (!postId){
      return [];
    }
    var key = String(postId);
    var store = readReactionStore();
    if (store && store[key]){
      return normalizeReactions(store[key]);
    }
    var config = getThrottleConfig();
    var cookie = readCookie(config.cookiePrefix + key);
    return normalizeReactions(cookie);
  }

  function setStoredReactions(postId, reactions){
    if (!postId){
      return;
    }
    var key = String(postId);
    var store = readReactionStore();
    var normalized = normalizeReactions(reactions);
    if (normalized.length){
      store[key] = normalized;
    } else {
      delete store[key];
    }
    writeReactionStore(store);
    var config = getThrottleConfig();
    if (normalized.length){
      writeCookie(config.cookiePrefix + key, JSON.stringify(normalized), config.cookieTtl);
    } else {
      writeCookie(config.cookiePrefix + key, '', -1);
    }
  }

  function highlightReactions(bar, reactions){
    if (!bar){
      return;
    }
    var active = normalizeReactions(reactions);
    var map = {};
    active.forEach(function(slug){
      map[slug] = true;
    });
    var buttons = bar.querySelectorAll('.waki-reaction');
    Array.prototype.forEach.call(buttons, function(button){
      var slug = button.getAttribute('data-reaction');
      var isActive = !!map[slug];
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
    if (active.length){
      bar.setAttribute('data-user', active.join(','));
    } else {
      bar.removeAttribute('data-user');
    }
  }

  function triggerFirstReactionCelebration(button){
    if (!button){
      return;
    }
    button.classList.add('is-first-reaction');
    window.setTimeout(function(){
      button.classList.remove('is-first-reaction');
    }, 700);
  }

  function updateReactionCounts(bar, counts){
    if (!bar || !counts){
      return;
    }
    var buttons = bar.querySelectorAll('.waki-reaction');
    Array.prototype.forEach.call(buttons, function(button){
      var slug = button.getAttribute('data-reaction');
      var value = Object.prototype.hasOwnProperty.call(counts, slug) ? parseInt(counts[slug], 10) : 0;
      if (isNaN(value)){
        value = 0;
      }
      var target = button.querySelector('[data-your-share-reaction-count]');
      if (target){
        var visible = value > 0;
        target.textContent = String(value);
        target.setAttribute('data-visible', visible ? '1' : '0');
        target.setAttribute('aria-hidden', visible ? 'false' : 'true');
        button.classList.toggle('has-count', visible);
      }
    });
  }

  function getRestUrl(path){
    if (!reactionsConfig || !reactionsConfig.rest || !reactionsConfig.rest.root){
      return '';
    }
    var root = reactionsConfig.rest.root;
    if (root.charAt(root.length - 1) === '/'){
      root = root.slice(0, -1);
    }
    if (path.charAt(0) !== '/'){
      path = '/' + path;
    }
    return root + path;
  }

  function getRestHeaders(){
    var headers = {};
    if (reactionsConfig && reactionsConfig.rest && reactionsConfig.rest.nonce){
      headers['X-WP-Nonce'] = reactionsConfig.rest.nonce;
    }
    return headers;
  }

  function fetchReactionSummary(postId){
    if (typeof window.fetch !== 'function'){
      return Promise.resolve(null);
    }
    var base = getRestUrl('/summary');
    if (!base){
      return Promise.resolve(null);
    }
    var url = base + '?post_id=' + encodeURIComponent(postId);
    return fetch(url, {
      credentials: 'same-origin',
      headers: getRestHeaders()
    }).then(function(response){
      if (!response.ok){
        throw new Error('Failed to fetch reactions');
      }
      return response.json();
    });
  }

  function sendReaction(postId, reaction, intent){
    if (typeof window.fetch !== 'function'){
      return Promise.reject(new Error('Fetch unavailable'));
    }
    var url = getRestUrl('/react');
    if (!url){
      return Promise.reject(new Error('REST unavailable'));
    }
    var headers = getRestHeaders();
    headers['Content-Type'] = 'application/json';
    var payload = { post_id: postId, reaction: reaction };
    if (intent){
      payload.intent = intent;
    }
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: headers,
      body: JSON.stringify(payload)
    }).then(function(response){
      if (!response.ok){
        return response.json().then(function(body){
          throw body;
        }).catch(function(){
          throw new Error('Request failed');
        });
      }
      return response.json();
    });
  }

  function setupReactionBar(bar){
    if (!bar || bar.getAttribute('data-your-share-reactions-ready') === '1'){
      return;
    }
    bar.setAttribute('data-your-share-reactions-ready', '1');
    var postId = parseInt(bar.getAttribute('data-post'), 10);
    if (!postId){
      return;
    }

    var paletteState = null;
    var paletteToggle = bar.querySelector('[data-your-share-reaction-toggle]');
    var palette = null;
    var openPaletteFn = null;
    var closePaletteFn = null;

    if (paletteToggle){
      var paletteId = paletteToggle.getAttribute('aria-controls') || '';
      if (paletteId){
        palette = document.getElementById(paletteId);
      }
      if (!palette){
        palette = bar.querySelector('[data-your-share-reaction-palette]');
      }
    }

    if (palette && paletteToggle){
      paletteState = {
        toggle: paletteToggle,
        panel: palette,
        open: false,
        onDocumentClick: null,
        onKeydown: null,
        close: function(returnFocus){
          if (closePaletteFn){
            closePaletteFn(returnFocus);
          }
        }
      };

      closePaletteFn = function(returnFocus){
        if (!paletteState.open){
          return;
        }
        paletteState.open = false;
        paletteToggle.setAttribute('aria-expanded', 'false');
        paletteToggle.classList.remove('is-open');
        palette.setAttribute('aria-hidden', 'true');
        palette.setAttribute('hidden', 'hidden');
        if (paletteState.onDocumentClick){
          document.removeEventListener('click', paletteState.onDocumentClick);
          paletteState.onDocumentClick = null;
        }
        if (paletteState.onKeydown){
          document.removeEventListener('keydown', paletteState.onKeydown);
          paletteState.onKeydown = null;
        }
        if (openPaletteState === paletteState){
          openPaletteState = null;
        }
        if (returnFocus && paletteToggle.focus){
          paletteToggle.focus({ preventScroll: true });
        }
      };

      function handlePaletteDocument(event){
        if (!palette.contains(event.target) && !paletteToggle.contains(event.target)){
          closePaletteFn(false);
        }
      }

      function handlePaletteKeydown(event){
        if (!paletteState.open){
          return;
        }
        if (event.key === 'Escape' || event.key === 'Esc'){
          event.preventDefault();
          closePaletteFn(true);
          return;
        }
        if (event.key === 'Tab'){
          var focusable = getFocusableElements(palette);
          if (!focusable.length){
            event.preventDefault();
            palette.focus({ preventScroll: true });
            return;
          }
          var first = focusable[0];
          var last = focusable[focusable.length - 1];
          var active = document.activeElement;
          if (event.shiftKey){
            if (active === first || !palette.contains(active)){
              event.preventDefault();
              last.focus({ preventScroll: true });
            }
          } else if (active === last){
            event.preventDefault();
            first.focus({ preventScroll: true });
          }
        }
      }

      openPaletteFn = function(){
        if (paletteState.open){
          return;
        }
        if (openPaletteState && openPaletteState.close){
          openPaletteState.close(false);
        }
        openPaletteState = paletteState;
        paletteState.open = true;
        paletteToggle.setAttribute('aria-expanded', 'true');
        paletteToggle.classList.add('is-open');
        palette.removeAttribute('hidden');
        palette.setAttribute('aria-hidden', 'false');
        paletteState.onDocumentClick = handlePaletteDocument;
        paletteState.onKeydown = handlePaletteKeydown;
        document.addEventListener('click', handlePaletteDocument);
        document.addEventListener('keydown', handlePaletteKeydown);
        var focusable = getFocusableElements(palette);
        if (focusable.length){
          focusable[0].focus({ preventScroll: true });
        } else if (palette.focus){
          palette.focus({ preventScroll: true });
        }
      };

      paletteState.close = closePaletteFn;

      paletteToggle.addEventListener('click', function(event){
        event.preventDefault();
        if (paletteState.open){
          closePaletteFn(true);
        } else if (openPaletteFn){
          openPaletteFn();
        }
      });
    }

    var supportsFetch = typeof window.fetch === 'function';
    var initial = normalizeReactions(bar.getAttribute('data-user'));
    if (!initial.length){
      initial = getStoredReactions(postId);
    }
    if (initial.length){
      highlightReactions(bar, initial);
    }

    if (supportsFetch){
      fetchReactionSummary(postId).then(function(payload){
        if (!payload){
          return;
        }
        if (payload.counts){
          updateReactionCounts(bar, payload.counts);
        }
        if (payload.user_reactions){
          var reactions = normalizeReactions(payload.user_reactions);
          highlightReactions(bar, reactions);
          setStoredReactions(postId, reactions);
        } else if (payload.user_reaction){
          var fallback = normalizeReactions([payload.user_reaction]);
          highlightReactions(bar, fallback);
          setStoredReactions(postId, fallback);
        }
      }).catch(function(){
        // ignore network errors
      });
    }

    var pending = false;
    var firstCelebrated = initial.length > 0;
    bar.addEventListener('click', function(event){
      var toggleTarget = event.target.closest('[data-your-share-reaction-toggle]');
      if (toggleTarget && paletteState && paletteState.toggle === toggleTarget){
        event.preventDefault();
        if (paletteState.open){
          closePaletteFn(true);
        } else if (openPaletteFn){
          openPaletteFn();
        }
        return;
      }
      var target = event.target.closest('.waki-reaction');
      if (!target){
        return;
      }
      event.preventDefault();
      grantInteractionConsent();
      var slug = target.getAttribute('data-reaction');
      if (!slug){
        return;
      }
      if (pending){
        return;
      }
      if (!supportsFetch){
        return;
      }
      var current = getStoredReactions(postId);
      var isActive = current.indexOf(slug) !== -1;
      var intent = isActive ? 'remove' : 'add';
      var optimistic = current.slice();
      if (isActive){
        optimistic = optimistic.filter(function(item){ return item !== slug; });
      } else {
        optimistic.push(slug);
      }
      var celebrateFirst = !current.length && !isActive && !firstCelebrated;
      if (celebrateFirst){
        triggerFirstReactionCelebration(target);
        firstCelebrated = true;
      }
      highlightReactions(bar, optimistic);
      pending = true;
      var fromPalette = paletteState && paletteState.panel && paletteState.panel.contains(target);
      sendReaction(postId, slug, intent).then(function(body){
        pending = false;
        if (body && body.counts){
          updateReactionCounts(bar, body.counts);
        }
        var final = optimistic;
        if (body && body.user_reactions){
          final = normalizeReactions(body.user_reactions);
        }
        setStoredReactions(postId, final);
        highlightReactions(bar, final);
        if (body && body.status === 'added'){
          trackReaction(bar, postId, slug);
        }
        if (fromPalette && paletteState && paletteState.close){
          paletteState.close(false);
        }
      }).catch(function(error){
        pending = false;
        var fallback = getStoredReactions(postId);
        if (error && error.data && error.data.user_reactions){
          fallback = normalizeReactions(error.data.user_reactions);
          setStoredReactions(postId, fallback);
        }
        highlightReactions(bar, fallback);
        if (error && error.data && error.data.user_reaction && !error.data.user_reactions){
          var locked = normalizeReactions([error.data.user_reaction]);
          if (locked.length){
            setStoredReactions(postId, locked);
            highlightReactions(bar, locked);
          }
        }
        if (fromPalette && paletteState && paletteState.close){
          paletteState.close(false);
        }
      });
    });
  }

  function initReactions(){
    var bars = document.querySelectorAll('[data-your-share-reactions]');
    if (!bars.length){
      return;
    }

    Array.prototype.forEach.call(bars, function(bar){
      setupReactionBar(bar);
    });
  }

  function getShareSheetBreakpoint(wrapper){
    var fallback = shareSheetBreakpointFallback;
    if (!wrapper){
      return fallback;
    }
    try {
      var style = window.getComputedStyle(wrapper);
      var raw = (style.getPropertyValue('--waki-sheet-breakpoint') || '').trim();
      if (raw){
        var parsed = parseInt(raw, 10);
        if (!isNaN(parsed)){
          return parsed;
        }
      }
    } catch (error) {
      // ignore style access issues
    }
    return fallback;
  }

  function shouldUseShareSheet(wrapper){
    var viewport = window.innerWidth || document.documentElement.clientWidth || 0;
    var breakpoint = getShareSheetBreakpoint(wrapper);
    return viewport <= breakpoint;
  }

  function setShareSheetMode(wrapper){
    if (!wrapper || !wrapper.querySelector){
      return;
    }
    var panel = wrapper.querySelector('[data-your-share-extra]');
    if (!panel){
      wrapper.classList.remove('is-sheet-mode');
      wrapper.classList.remove('is-popover-mode');
      return;
    }
    var useSheet = shouldUseShareSheet(wrapper);
    wrapper.classList.toggle('is-sheet-mode', useSheet);
    wrapper.classList.toggle('is-popover-mode', !useSheet);
    panel.setAttribute('aria-modal', useSheet ? 'true' : 'false');
  }

  function updateShareSheetModes(){
    var wrappers = document.querySelectorAll('.waki-share');
    if (!wrappers.length){
      return;
    }
    Array.prototype.forEach.call(wrappers, function(wrapper){
      setShareSheetMode(wrapper);
    });
    if (sheetState && sheetState.wrapper){
      var shouldBeSheet = sheetState.wrapper.classList.contains('is-sheet-mode');
      if ((sheetState.mode === 'sheet' && !shouldBeSheet) || (sheetState.mode === 'popover' && shouldBeSheet)){
        closeShareSheet(sheetState, false);
      }
    }
  }

  function updateFloatingVisibility(){
    var shareBars = document.querySelectorAll('.waki-share-floating');
    var reactionBars = document.querySelectorAll('.waki-reactions-floating');
    if (!shareBars.length && !reactionBars.length) {
      return;
    }

    var viewport = window.innerWidth || document.documentElement.clientWidth;

    function toggle(bar){
      var style = window.getComputedStyle(bar);
      var breakpoint = parseInt(style.getPropertyValue('--waki-breakpoint') || '1024', 10);
      if (viewport < breakpoint) {
        bar.style.display = 'none';
      } else {
        bar.style.display = '';
      }
    }

    Array.prototype.forEach.call(shareBars, toggle);
    Array.prototype.forEach.call(reactionBars, toggle);
  }

  function closeShareSheet(state, returnFocus){
    if (!state){
      state = sheetState;
    }
    if (!state){
      return;
    }
    if (state.backdrop){
      state.backdrop.removeEventListener('click', state.onBackdrop);
      if (state.backdrop.parentNode){
        state.backdrop.parentNode.removeChild(state.backdrop);
      }
    }
    if (state.mode === 'popover' && state.onPointerDown){
      document.removeEventListener('pointerdown', state.onPointerDown);
    }
    if (state.panel){
      state.panel.setAttribute('hidden', 'hidden');
      state.panel.setAttribute('aria-hidden', 'true');
      if (typeof state.panel.hidden !== 'undefined'){
        state.panel.hidden = true;
      }
    }
    if (state.wrapper){
      state.wrapper.classList.remove('is-sheet-active');
    }
    if (state.button){
      state.button.setAttribute('aria-expanded', 'false');
      state.button.classList.remove('is-active');
    }
    document.removeEventListener('keydown', state.onKeydown);
    if (sheetState === state){
      sheetState = null;
    }
    if (returnFocus){
      var target = state.button || state.previousFocus;
      if (target && target.focus){
        target.focus({ preventScroll: true });
      }
    }
  }

  function openShareSheet(wrapper, button, panel){
    if (!panel){
      return;
    }
    setShareSheetMode(wrapper);
    var useSheet = wrapper.classList.contains('is-sheet-mode');
    if (sheetState && sheetState.button === button){
      closeShareSheet(sheetState, true);
      return;
    }
    if (sheetState){
      closeShareSheet(sheetState, false);
    }
    if (openPaletteState && openPaletteState.close){
      openPaletteState.close(false);
    }
    button.setAttribute('aria-expanded', 'true');
    button.classList.add('is-active');
    panel.removeAttribute('hidden');
    panel.setAttribute('aria-hidden', 'false');
    if (typeof panel.hidden !== 'undefined'){
      panel.hidden = false;
    }
    wrapper.classList.add('is-sheet-active');

    var previousFocus = document.activeElement;
    var state = {
      wrapper: wrapper,
      button: button,
      panel: panel,
      backdrop: null,
      previousFocus: previousFocus,
      onKeydown: null,
      onBackdrop: null,
      onPointerDown: null,
      mode: useSheet ? 'sheet' : 'popover'
    };

    if (useSheet){
      var backdrop = document.createElement('div');
      backdrop.className = 'waki-share-sheet-backdrop';
      backdrop.setAttribute('data-your-share-sheet-backdrop', '1');
      document.body.appendChild(backdrop);
      state.backdrop = backdrop;
      state.onBackdrop = function(event){
        event.preventDefault();
        closeShareSheet(state, true);
      };
      backdrop.addEventListener('click', state.onBackdrop);
    } else {
      state.onPointerDown = function(event){
        if (!panel.contains(event.target) && !button.contains(event.target)){
          closeShareSheet(state, false);
        }
      };
      window.setTimeout(function(){
        document.addEventListener('pointerdown', state.onPointerDown);
      }, 0);
    }

    state.onKeydown = function(event){
      if (event.key === 'Escape' || event.key === 'Esc'){
        event.preventDefault();
        closeShareSheet(state, true);
        return;
      }
      if (event.key === 'Tab'){
        var focusable = getFocusableElements(panel);
        if (!focusable.length){
          event.preventDefault();
          panel.focus({ preventScroll: true });
          return;
        }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        var active = document.activeElement;
        if (event.shiftKey){
          if (active === first || !panel.contains(active)){
            event.preventDefault();
            last.focus({ preventScroll: true });
          }
        } else if (active === last){
          event.preventDefault();
          first.focus({ preventScroll: true });
        }
      }
    };

    document.addEventListener('keydown', state.onKeydown);

    sheetState = state;

    var focusable = getFocusableElements(panel);
    if (focusable.length){
      focusable[0].focus({ preventScroll: true });
    } else if (panel.focus){
      panel.focus({ preventScroll: true });
    }
  }

  function celebrateSheetShare(wrapper){
    if (!wrapper){
      return;
    }
    try {
      if (window.sessionStorage && sessionStorage.getItem(sheetConfettiKey) === '1'){
        return;
      }
      if (window.sessionStorage){
        sessionStorage.setItem(sheetConfettiKey, '1');
      }
    } catch (error) {
      // sessionStorage may be unavailable
    }
    if (sheetCelebrated){
      return;
    }
    sheetCelebrated = true;
    var host = wrapper;
    if (sheetState && sheetState.wrapper === wrapper && sheetState.panel){
      host = sheetState.panel;
    }
    if (!host){
      return;
    }
    var confetti = document.createElement('div');
    confetti.className = 'waki-share-confetti';
    confetti.setAttribute('aria-hidden', 'true');
    for (var i = 0; i < 18; i++){
      var piece = document.createElement('span');
      piece.className = 'waki-share-confetti__piece';
      piece.style.setProperty('--waki-confetti-x', (Math.random() * 160 - 80) + 'px');
      piece.style.setProperty('--waki-confetti-delay', Math.round(Math.random() * 120) + 'ms');
      piece.style.setProperty('--waki-confetti-duration', Math.round(900 + Math.random() * 400) + 'ms');
      piece.style.setProperty('--waki-confetti-rotate', Math.round(Math.random() * 720 - 360) + 'deg');
      piece.style.setProperty('--waki-confetti-hue', Math.round(Math.random() * 360));
      confetti.appendChild(piece);
    }
    host.appendChild(confetti);
    window.setTimeout(function(){
      if (confetti && confetti.parentNode){
        confetti.parentNode.removeChild(confetti);
      }
    }, 1800);
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
    if (!button){
      return;
    }

    var wrapper = button.closest('.waki-share');
    if (!wrapper){
      return;
    }

    if (wrapper.classList.contains('waki-follow')){
      return;
    }

    var fromSheet = !!button.closest('.waki-share-extra');
    grantInteractionConsent();

    if (button.hasAttribute('data-share-toggle')){
      event.preventDefault();
      var targetId = button.getAttribute('aria-controls') || '';
      var panel = targetId ? document.getElementById(targetId) : wrapper.querySelector('[data-your-share-extra]');
      if (!panel){
        return;
      }
      if (sheetState && sheetState.button === button){
        closeShareSheet(sheetState, true);
      } else {
        openShareSheet(wrapper, button, panel);
      }
      return;
    }

    if (button.closest('.waki-share-overlay')){
      closeAllOverlays(null);
    }

    var network = button.getAttribute('data-net');
    var context = getShareContext(wrapper);

    if (network === 'copy') {
      event.preventDefault();
      trackShare(wrapper, network, context.url);
      handleCopy(context.url, context.snippet);
      if (fromSheet){
        celebrateSheetShare(wrapper);
      }
      return;
    }

    if (network === 'native') {
      event.preventDefault();
      trackShare(wrapper, network, context.url);
      if (fromSheet){
        celebrateSheetShare(wrapper);
      }
      if (navigator.share) {
        var shareData = {
          title: context.title || document.title,
          url: context.url
        };
        if (context.snippet){
          shareData.text = context.snippet;
        }
        navigator.share(shareData).catch(function(){});
      } else {
        toast(messages.shareUnsupported || 'Sharing not supported');
      }
      return;
    }

    var href = button.getAttribute('href') || '';
    if (href.indexOf('mailto:') === 0 || href.indexOf('wa.me') !== -1) {
      trackShare(wrapper, network, href);
      if (fromSheet){
        celebrateSheetShare(wrapper);
      }
      return;
    }

    event.preventDefault();
    trackShare(wrapper, network, href);
    if (fromSheet){
      celebrateSheetShare(wrapper);
    }
    openPopup(href);
  });

  function handleResize(){
    updateShareSheetModes();
    updateFloatingVisibility();
  }

  window.addEventListener('resize', handleResize);

  function attachConsentListener(type){
    var onceHandler = function(){
      document.removeEventListener(type, onceHandler);
      grantInteractionConsent();
    };
    try {
      document.addEventListener(type, onceHandler, { once: true });
    } catch (error) {
      document.addEventListener(type, onceHandler);
    }
  }

  attachConsentListener('pointerdown');
  attachConsentListener('keydown');

  window.yourShareConsent = window.yourShareConsent || {};
  window.yourShareConsent.register = function(fn){
    deferSdkLoader(fn);
  };
  window.yourShareConsent.grant = grantInteractionConsent;
  window.yourShareConsent.granted = function(){
    return interactionGranted;
  };

  function onReady(){
    hydrateGeo();
    updateShareSheetModes();
    updateFloatingVisibility();
    initReactions();
    initMediaOverlays();
    deferSdkLoader(hydrateCounts);
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', onReady);
  } else {
    onReady();
  }

  document.addEventListener('yourShareRefreshCounts', function(event){
    var detail = event && event.detail ? event.detail : {};
    var target = detail.target || detail.selector || (event.target && event.target.classList && event.target.classList.contains('waki-share') ? event.target : null);
    refreshCounts(target, !!detail.force);
  });
})();
