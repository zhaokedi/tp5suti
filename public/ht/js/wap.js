CKEDITOR.on('instanceReady', function (ev) {
    var editor = ev.editor,
        dataProcessor = editor.dataProcessor,
        htmlFilter = dataProcessor && dataProcessor.htmlFilter;

    // Out self closing tags the HTML4 way, like <br>.
    dataProcessor.writer.selfClosingEnd = '>';

    // Make output formatting behave similar to FCKeditor
    var dtd = CKEDITOR.dtd;
    for (var e in CKEDITOR.tools.extend({}, dtd.$nonBodyContent, dtd.$block, dtd.$listItem, dtd.$tableContent)) {
        dataProcessor.writer.setRules(e,
            {
                indent: true,
                breakBeforeOpen: true,
                breakAfterOpen: false,
                breakBeforeClose: !dtd[e]['#'],
                breakAfterClose: true
            });
    }

    // Output properties as attributes, not styles.
    htmlFilter.addRules(
        {
            elements:
            {
                $: function (element) {
                    // Output dimensions of images as width and height
                    if (element.name == 'img') {
                        var style = element.attributes.style;
                        delete element.attributes.style;
                    }

                    if (!element.attributes.style)
                        delete element.attributes.style;

                    return element;
                }
            },

            attributes:
            {
                style: function (value, element) {
                    // Return #RGB for background and border colors
                    return convertRGBToHex(value);
                }
            }
        });
});