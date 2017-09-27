/**
 * @license Copyright (c) 2003-2015, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here. For example:
	// config.language = 'fr';
	// config.uiColor = '#AADC6E';
//默认不填为全部工具
    config.toolbar = 'Basic';
    config.enterMode = CKEDITOR.ENTER_BR;
    config.shiftEnterMode = CKEDITOR.ENTER_P;
    config.toolbar_Basic =

      [
        ['Source','Bold', 'Italic','Underline','-','JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock',  '-','Font','FontSize', 'Link', 'Unlink','-','Image','TextColor','BGColor']
      ];


  if(typeof(xccinfig)!='undefined'&& JSON.stringify(xccinfig) !='{}')
  {

    config.filebrowserBrowseUrl=xccinfig["filebrowserBrowseUrl"];
    config.filebrowserUploadUrl=xccinfig["filebrowserUploadUrl"];
     config.allowedContent =true;


  }

};
