/**
 * Form Editors
 */

'use strict';

(function () {
  // Snow Theme
  // --------------------------------------------------------------------
  // const snowEditor = new Quill('#snow-editor', {
  //   bounds: '#snow-editor',
  //   modules: {
  //     syntax: true,
  //     toolbar: '#snow-toolbar'
  //   },
    
  //   theme: 'snow'
  // });

  // Bubble Theme
  // --------------------------------------------------------------------
  // const bubbleEditor = new Quill('#bubble-editor', {
  //   modules: {
  //     toolbar: '#bubble-toolbar'
  //   },
  //   theme: 'bubble'
  // });

  // Full Toolbar
  // --------------------------------------------------------------------
  const fullToolbar = [
    [
      {
        font: [  ]
      },
      {
        size: []
      }
    ],
    ['bold', 'italic', 'underline', 'strike'],
    [
      {
        color: []
      },
      {
        background: []
      }
    ],
    [
      {
        script: 'super'
      },
      {
        script: 'sub'
      }
    ],
    [
      {
        header: '1'
      },
      {
        header: '2'
      },
      'blockquote',
      'code-block'
    ],
    [
      {
        list: 'ordered'
      },
      {
        indent: '-1'
      },
      {
        indent: '+1'
      }
    ],
    [{ direction: 'rtl' }, { align: [] }],
    ['link', 'image', 'video', 'formula'],
    ['clean']
  ];

  $('.custom_editor').each(function() {
    const $content = $(this).find('.custom_editor_content').get(0);
    const $textarea = $(this).find('.custom_editor_textarea');
    const fullEditor = new Quill($content, {
        bounds: $content,
        placeholder: 'Type Something...',
        modules: {
          syntax: true,
          toolbar: fullToolbar
        },
      theme: 'snow'
    });
    fullEditor.on('text-change', function(delta, oldDelta, source) {
      $textarea.val(fullEditor.getSemanticHTML());
    });
    $textarea.val(fullEditor.getSemanticHTML());
  });
})();
