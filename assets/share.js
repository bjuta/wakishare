(function(){
  var messages = window.yourShareMessages || {};
  var reactionsConfig = window.yourShareReactions || {};
  var reactionStore = null;

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
    var button = event.target.closest('.waki-btn');
    if (!button || !button.closest('.waki-share')) {
      return;
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
    initReactions();
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', onReady);
  } else {
    onReady();
  }
})();
