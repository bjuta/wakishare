(function(){
  var messages = window.yourShareMessages || {};
  var reactionsConfig = window.yourShareReactions || {};
  var analyticsConfig = window.yourShareAnalytics || {};
  var reactionStore = null;
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

      badge.textContent = formatCount(total);
      badge.setAttribute('data-value', total);
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
    if (ttl){
      var date = new Date();
      date.setTime(date.getTime() + (ttl * 1000));
      expires = '; expires=' + date.toUTCString();
    }
    document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
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
            store = parsed;
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
    reactionStore = store;
    var config = getThrottleConfig();
    try {
      if (window.localStorage){
        window.localStorage.setItem(config.storageKey, JSON.stringify(store));
      }
    } catch (error) {
      // ignore storage errors
    }
  }

  function getStoredReaction(postId){
    if (!postId){
      return '';
    }
    var key = String(postId);
    var store = readReactionStore();
    if (store && store[key]){
      return store[key];
    }
    var config = getThrottleConfig();
    var cookie = readCookie(config.cookiePrefix + key);
    return cookie || '';
  }

  function setStoredReaction(postId, reaction){
    if (!postId || !reaction){
      return;
    }
    var key = String(postId);
    var store = readReactionStore();
    store[key] = reaction;
    writeReactionStore(store);
    var config = getThrottleConfig();
    writeCookie(config.cookiePrefix + key, reaction, config.cookieTtl);
  }

  function highlightReaction(bar, reaction){
    if (!bar){
      return;
    }
    var buttons = bar.querySelectorAll('.waki-reaction');
    Array.prototype.forEach.call(buttons, function(button){
      var slug = button.getAttribute('data-reaction');
      var isActive = !!reaction && slug === reaction;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
    if (reaction){
      bar.setAttribute('data-user', reaction);
    } else {
      bar.removeAttribute('data-user');
    }
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
        target.textContent = String(value);
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

  function sendReaction(postId, reaction){
    if (typeof window.fetch !== 'function'){
      return Promise.reject(new Error('Fetch unavailable'));
    }
    var url = getRestUrl('/react');
    if (!url){
      return Promise.reject(new Error('REST unavailable'));
    }
    var headers = getRestHeaders();
    headers['Content-Type'] = 'application/json';
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: headers,
      body: JSON.stringify({ post_id: postId, reaction: reaction })
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

    var supportsFetch = typeof window.fetch === 'function';
    var stored = getStoredReaction(postId) || bar.getAttribute('data-user') || '';
    if (stored){
      highlightReaction(bar, stored);
    }

    if (supportsFetch){
      fetchReactionSummary(postId).then(function(payload){
        if (!payload){
          return;
        }
        if (payload.counts){
          updateReactionCounts(bar, payload.counts);
        }
        if (payload.user_reaction){
          highlightReaction(bar, payload.user_reaction);
          setStoredReaction(postId, payload.user_reaction);
        }
      }).catch(function(){
        // ignore network errors
      });
    }

    var pending = false;
    bar.addEventListener('click', function(event){
      var target = event.target.closest('.waki-reaction');
      if (!target){
        return;
      }
      event.preventDefault();
      var slug = target.getAttribute('data-reaction');
      if (!slug){
        return;
      }
      var current = getStoredReaction(postId);
      if (current){
        highlightReaction(bar, current);
        return;
      }
      if (!supportsFetch){
        return;
      }
      if (pending){
        return;
      }
      pending = true;
      sendReaction(postId, slug).then(function(body){
        pending = false;
        if (body && body.counts){
          updateReactionCounts(bar, body.counts);
        }
        setStoredReaction(postId, slug);
        highlightReaction(bar, slug);
        trackReaction(bar, postId, slug);
      }).catch(function(error){
        pending = false;
        if (error && error.data && error.data.user_reaction){
          var locked = error.data.user_reaction;
          setStoredReaction(postId, locked);
          highlightReaction(bar, locked);
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

    if (button.closest('.waki-share-overlay')){
      closeAllOverlays(null);
    }

    var network = button.getAttribute('data-net');

      if (network === 'copy') {
        event.preventDefault();
      trackShare(wrapper, network, window.location.href);
      handleCopy();
      return;
    }

    if (network === 'native') {
      event.preventDefault();
      trackShare(wrapper, network, window.location.href);
      if (navigator.share) {
        navigator.share({ title: document.title, url: window.location.href }).catch(function(){});
      } else {
        toast(messages.shareUnsupported || 'Sharing not supported');
      }
      return;
    }

    var href = button.getAttribute('href') || '';
    if (href.indexOf('mailto:') === 0 || href.indexOf('wa.me') !== -1) {
      trackShare(wrapper, network, href);
      return;
    }

    event.preventDefault();
    trackShare(wrapper, network, href);
    openPopup(href);
  });

  window.addEventListener('resize', updateFloatingVisibility);

  function onReady(){
    hydrateGeo();
    updateFloatingVisibility();
    initReactions();
    initMediaOverlays();
    hydrateCounts();
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
