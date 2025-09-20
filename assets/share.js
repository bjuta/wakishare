(function(){
  var messages = window.yourShareMessages || {};
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
