(function ($) {
	"use strict";

	$(function () {

        $('#wpas-cancel-edit-main-ticket-message').hide();
        $('#wpas-save-edit-main-ticket-message').hide();

		/*
		Check if TinyMCE is active in WordPress
		http://stackoverflow.com/a/1180199/1414881
		 */
		var is_tinyMCE_active = false;
		if (typeof (tinyMCE) != "undefined") {
			if (tinyMCE.activeEditor === null || tinyMCE.activeEditor.isHidden() !== false) {
				is_tinyMCE_active = true;
			}
		}

		if (is_tinyMCE_active) {
            
            /**
             * Edit button
             */
			$(document).on('click', '#wpas-edit-main-ticket-message', function (event) {
                event.preventDefault();
                $('#wpas-edit-main-ticket-message').hide();
                $('#wpas-view-edit-main-ticket-message').hide();
                $('#wpas-cancel-edit-main-ticket-message').show();
                $('#wpas-save-edit-main-ticket-message').show();

                // AJAX data
				var data = {
					'action': 'wp_editor_content_ajax',
                    'post_id':  $(this).data('ticketid')
                };

                /**
                 * Remove content
                 */
                $('.wpas-main-ticket-message').empty();
                $('.wpas-main-ticket-message').html('<textarea id="wpas-main-ticket-message-editor" style="width: 100%; border: none; height: 350px;"></textarea>');

                // AJAX request
				$.post(ajaxurl, data, function (response) {
                    setTimeout(function() {
                        wpas_init_editor( 'wpas-main-ticket-message-editor', response );
                    }, 100);
				});
                    
            });

             /**
             * Save button
             */
            $(document).on('click', '#wpas-save-edit-main-ticket-message', function (event) {
                event.preventDefault();

                var content = tinyMCE.get('wpas-main-ticket-message-editor').getContent();                
                var data = {
                    'action': 'wpas_edit_ticket_content',
                    'post_id':  $(this).data('ticketid'),
                    'content': content
                };

                $.post(ajaxurl, data, function (response) {
                    $('#wpas-edit-main-ticket-message').show();
                    $('#wpas-view-edit-main-ticket-message').show();
                    $('#wpas-cancel-edit-main-ticket-message').hide();
                    $('#wpas-save-edit-main-ticket-message').hide();

                    /**
                     * Remove content
                     */
                    $('.wpas-main-ticket-message').empty();

                    /**
                     * Remove tinyMCE
                     */
                    tinyMCE.get('wpas-main-ticket-message-editor').remove();

                    if( response.code === 200 ) {
                        $('.wpas-main-ticket-message').html(content);
                    }else{
                        if(response.content){
                            $('.wpas-main-ticket-message').html(response.content);
                        }
                    }                    
                });

            });
            
             /**
             * Cancel button
             */
            $(document).on('click', '#wpas-cancel-edit-main-ticket-message', function (event) {
                event.preventDefault();
                var data = {
                    'action': 'wp_editor_content_ajax',
                    'post_id':  $(this).data('ticketid')
                };

                $.post(ajaxurl, data, function (response) {
                    $('#wpas-edit-main-ticket-message').show();
                    $('#wpas-view-edit-main-ticket-message').show();
                    $('#wpas-cancel-edit-main-ticket-message').hide();
                    $('#wpas-save-edit-main-ticket-message').hide();                    

                    /**
                     * Remove content
                     */
                    $('.wpas-main-ticket-message').empty();

                    /**
                     * Remove tinyMCE
                     */
                    tinyMCE.get('wpas-main-ticket-message-editor').remove();
                    $('.wpas-main-ticket-message').html(response);
                });

            });

        } 


        /**
         * Set content on WP Editor
         * 
         * @param {*} content 
         * @param {*} editor_id 
         * @param {*} textarea_id 
         */
        function wpas_set_editor_content( content, editor_id, textarea_id ){
            if ( typeof editor_id == 'undefined' ) editor_id = wpActiveEditor;
            if ( typeof textarea_id == 'undefined' ) textarea_id = editor_id;
        
            if ( jQuery('#wp-'+editor_id+'-wrap').hasClass('tmce-active') && tinyMCE.get(editor_id) ) {
                return tinyMCE.get(editor_id).setContent(content);
            }else{
                return jQuery('#'+textarea_id).val(content);
            }
        }

        /**
         * Initialize WP Editor through Javascript
         * 
         * @param {*} this_id 
         * @param {*} content 
         */
        function wpas_init_editor( this_id, content ){
            /**
             * Prepare the standard settings. Include the media buttons plus toolbars
             */
            var settings = {
                mediaButtons:	false,
                tinymce:	{
                    toolbar1: 'bold,italic,bullist,numlist,link,blockquote,alignleft,aligncenter,alignright,strikethrough,hr,forecolor,pastetext,removeformat,codeformat,undo,redo'
                },
                quicktags:		true,
            };
            /**
             * Initialize editor
            */
            wp.editor.initialize( this_id, settings );
            /**
             * Set editor content. This function set the 
             * editor content back to textarea as well
            */
            wpas_set_editor_content( content, this_id );
        }
                        
	});

}(jQuery));