(function () {
  'use strict';

  function rewriteTextNodes(root) {
    var walker = document.createTreeWalker(root || document.body, NodeFilter.SHOW_TEXT);
    var node;
    while ((node = walker.nextNode())) {
      if (!node || !node.nodeValue) {
        continue;
      }

      var text = node.nodeValue;
      var updated = text
        .replace(/\bAPI\s*KEY\b/gi, 'API Endpoint URL')
        .replace(/Enter your API key/gi, 'Enter your Ollama endpoint URL')
        .replace(/Your API key is stored securely\./gi, 'Your endpoint URL is stored in this setting.')
        .replace(/It was not possible to connect to the provider using this key\./gi, 'It was not possible to connect to the provider using this endpoint.');

      if (updated !== text) {
        node.nodeValue = updated;
      }
    }
  }

  function patchInputs(root) {
    var inputs = (root || document).querySelectorAll('input');
    inputs.forEach(function (input) {
      if (!input) {
        return;
      }

      var placeholder = input.getAttribute('placeholder') || '';
      if (/api\s*key/i.test(placeholder)) {
        input.setAttribute('placeholder', 'Enter your Ollama endpoint URL');
      }

      var ariaLabel = input.getAttribute('aria-label') || '';
      if (/api\s*key/i.test(ariaLabel)) {
        input.setAttribute('aria-label', ariaLabel.replace(/api\s*key/gi, 'API Endpoint URL'));
      }

      if (input.type === 'password') {
        input.setAttribute('type', 'text');
      }

      input.setAttribute('autocomplete', 'off');
    });
  }

  function applyAll() {
    rewriteTextNodes(document.body);
    patchInputs(document.body);
  }

  applyAll();
  document.addEventListener('DOMContentLoaded', applyAll);
  window.addEventListener('load', applyAll);

  var observer = new MutationObserver(function () {
    applyAll();
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true,
    characterData: true
  });
})();
