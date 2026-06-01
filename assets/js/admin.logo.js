/**
 * assets/js/admin.logo.js — Task 1: Logo upload preview + drag-drop
 * Include on admin logo page only.
 */
(function () {
  'use strict';

  var zone        = document.getElementById('logoDropZone');
  var fileInput   = document.getElementById('logoFileInput');
  var newPreview  = document.getElementById('logoNewPreview');
  var uploadInner = document.getElementById('logoUploadInner');
  var selectedName = document.getElementById('logoSelectedName');
  var submitBtn   = document.getElementById('logoSubmitBtn');

  if (!zone || !fileInput) return;

  function handleFile(file) {
    if (!file) return;

    // Client-side size check (2 MB)
    if (file.size > 2 * 1024 * 1024) {
      alert('File is too large. Maximum allowed size is 2 MB.');
      fileInput.value = '';
      return;
    }

    // Client-side type check
    var allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
    if (!allowed.includes(file.type)) {
      alert('Only PNG, JPG, JPEG, and WEBP images are allowed.');
      fileInput.value = '';
      return;
    }

    // Show preview
    var reader = new FileReader();
    reader.onload = function (e) {
      newPreview.src = e.target.result;
      newPreview.classList.add('visible');
      if (uploadInner) uploadInner.style.display = 'none';
    };
    reader.readAsDataURL(file);

    if (selectedName) selectedName.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
    if (submitBtn)   submitBtn.disabled = false;
  }

  // File input change
  fileInput.addEventListener('change', function () {
    if (fileInput.files && fileInput.files[0]) handleFile(fileInput.files[0]);
  });

  // Drag-over
  zone.addEventListener('dragover', function (e) {
    e.preventDefault();
    zone.classList.add('drag-over');
  });
  zone.addEventListener('dragleave', function () {
    zone.classList.remove('drag-over');
  });
  zone.addEventListener('drop', function (e) {
    e.preventDefault();
    zone.classList.remove('drag-over');
    var files = e.dataTransfer.files;
    if (files && files[0]) {
      // Assign to file input for form submission
      var dt = new DataTransfer();
      dt.items.add(files[0]);
      fileInput.files = dt.files;
      handleFile(files[0]);
    }
  });

})();