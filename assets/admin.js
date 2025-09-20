(function(){
  function updateNetworkCount(){
    var field = document.querySelector('[data-your-share-networks]');
    var output = document.querySelector('[data-your-share-networks-count]');
    if (!field || !output) {
      return;
    }
    var networks = field.value.split(',').map(function(item){
      return item.trim();
    }).filter(function(item){ return item.length > 0; });
    output.textContent = '(' + networks.length + ')';
  }

  document.addEventListener('DOMContentLoaded', function(){
    var field = document.querySelector('[data-your-share-networks]');
    if (field) {
      field.addEventListener('input', updateNetworkCount);
    }
    updateNetworkCount();
  });
})();
