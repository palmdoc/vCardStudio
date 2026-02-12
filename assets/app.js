(function () {
  function addSimpleInput(type) {
    const button = document.querySelector(`[data-add="${type}"]`);
    if (!button) return;

    button.addEventListener('click', function () {
      const max = Number(button.dataset.max || 1);
      const wrapper = document.getElementById(`${type}-wrapper`);
      const count = wrapper.querySelectorAll('input').length;
      if (count >= max) return;

      const input = document.createElement('input');
      input.name = `${type}[]`;
      input.placeholder = type === 'emails' ? 'email@example.com' : 'Phone number';
      input.type = type === 'emails' ? 'email' : 'text';
      wrapper.appendChild(input);
    });
  }

  const addLinkBtn = document.querySelector('[data-add-link]');
  if (addLinkBtn) {
    addLinkBtn.addEventListener('click', function () {
      const wrapper = document.getElementById('links-wrapper');
      const row = document.createElement('div');
      row.className = 'link-row';
      row.innerHTML = '<input type="text" name="link_labels[]" placeholder="Link label (optional)"><input type="text" name="link_urls[]" placeholder="https://example.com">';
      wrapper.appendChild(row);
    });
  }

  addSimpleInput('phones');
  addSimpleInput('emails');
})();
