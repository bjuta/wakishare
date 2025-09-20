(function(){
  var messages = window.yourShareMessages || {};

  function openPopup(url){
    var w = 600;
    var h = 500;
    var left = (screen.width - w) / 2;
    var top = (screen.height - h) / 2;
    window.open(url, 'yourShare', 'toolbar=0,status=0,width=' + w + ',height=' + h + ',top=' + top + ',left=' + left);
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
  document.addEventListener('DOMContentLoaded', updateFloatingVisibility);
})();
