'use strict';

(function () {
  const toolbar = [
    [{ font: [] }, { size: [] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ color: [] }, { background: [] }],
    [{ script: 'sub' }, { script: 'super' }],
    [{ header: '1' }, { header: '2' }, 'blockquote', 'code-block'],
    [{ list: 'ordered' }, { indent: '-1' }, { indent: '+1' }],
    [{ direction: 'rtl' }, { align: [] }],
    ['link', 'image', 'video'],
    ['clean']
  ];

  const quill = new Quill('#post-editor', {
    theme: 'snow',
    placeholder: 'Type something...',
    modules: {
      toolbar: toolbar
    }
  });

  // Sync content on submit
  document.querySelector('form').addEventListener('submit', function () {
    console.log('12222222222222');
    document.querySelector('#description').value = quill.root.innerHTML;
  });
  
})();
