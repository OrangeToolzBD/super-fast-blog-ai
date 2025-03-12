          /* ===== Ai Title Generate ===== */

            jQuery(document).ready(function($) {
                jQuery('#generate_button').on('click', function() {
                    var button = jQuery(this);
            
                    var title = jQuery('#blog_title').val();
                    var tlanguage = jQuery('#tlanguage').val();
                    var twstyle = jQuery('#twstyle').val();
                    var writone = jQuery('#writone').val();
                    var wvariation = jQuery('#wvariation').val();
                    
                    console.log(tlanguage);
                    console.log(twstyle);
                    console.log(writone);
                    console.log(wvariation);

                    jQuery('#blog_title').change('input', function() {
                        jQuery('#prompterror').fadeOut();                                  
                    });        
        
                    if (title === '') { 
                      jQuery('#prompterror').text('Please enter the blog title');
                      return;
                    }

                    if (title) {
                        jQuery.ajax({
                            url: ajax_ob.ajax_url,
                            method: 'POST',
                            dataType: 'JSON',
                            data: {
                                action: 'otslf_generate_blog_title',
                                blog_title: title,
                                tlanguage: tlanguage,
                                twstyle: twstyle,
                                writone: writone,
                                wvariation: wvariation,
                                nonce: ajax_ob.nonce
                            },
                            beforeSend: function(xhr) {
                                console.log('button', button);
                                button.append('<span class="loading-spinner"></span>');
                            },
                            success: function(response) {
                                if (response.success) {
                                    jQuery('#generated-titles').html(
                                        response.data.map(item => `<li>${item}</li>`).join('')
                                    );
                                    jQuery.toast({
                                        text: "Congratulations! Your blog title successfully generated.",
                                        heading: 'success', 
                                        icon: 'success', 
                                        showHideTransition: 'fade', 
                                        allowToastClose: true, 
                                        hideAfter: 300000, 
                                        stack: 15, 
                                        position: { left : 'auto', right : 100, top : 153, bottom : 'auto' },
                                        textAlign: 'left',
                                        loader: true, 
                                        loaderBg: '#9EC600',
                                        class: 'aitite-toast', 
                                    });    

                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 300);
            
                                } else {
                                    jQuery.toast({ text: 'An error: ' + response.data, 
                                        heading: 'Failed', 
                                        icon: 'error',
                                        showHideTransition: 'fade',
                                        allowToastClose: true, 
                                        hideAfter: 300000, 
                                        stack: 5, 
                                        position: 'top-right', 
                                        class: 'aitite-toast', 
                                       });
                                }
                            },
                            complete: function() {
                                button.find('.loading-spinner').remove();
                            },
                            error: function(error) {

                                jQuery.toast({ text: 'An error occurred.', 
                                    heading: 'Failed', 
                                    icon: 'error',
                                    showHideTransition: 'fade',
                                    allowToastClose: true, 
                                    hideAfter: 3000, 
                                    stack: 5, 
                                    position: 'top-right', 
                                   });

                            }
                        });
                    } else {
                    
                        jQuery.toast({ text: 'Please enter a blog post title', 
                            heading: 'Failed', 
                            icon: 'error',
                            showHideTransition: 'fade',
                            allowToastClose: true, 
                            hideAfter: 3000, 
                            stack: 5, 
                            position: 'top-right', 
                           });
                    }
                });
            });
        


                /* =============================== 
                        Ai Article generate 
                ================================= */            
                  

            jQuery(document).ready(function($) {
                // Toggle htaging and numberh fields when subheading changes
                jQuery("#subheading").on("change", function(){
                    if (jQuery(this).is(":checked")) {
                        jQuery("#htaging, #numberh").prop("disabled", false);
                    } else {
                        jQuery("#htaging, #numberh").prop("disabled", true);
                        // Optionally, clear any previous error messages for these fields
                        jQuery("#hgerror, #hnumbererror").fadeOut();
                    }
                }).trigger("change"); // Trigger once to set the initial state
            
                // Bind change events for error messages
                $('#prompt').on('change', function() {
                    jQuery('#prompterror').fadeOut();
                });
                jQuery('#woprompt').on('change', function() {
                    jQuery('#woprompterror').fadeOut();
                });
                jQuery('#countWord').on('change', function() {
                    jQuery('#maxerror').fadeOut();                                  
                });
                jQuery('#htaging').on('change', function() {
                    jQuery('#hgerror').fadeOut();                                  
                });
                jQuery('#numberh').on('change', function() {
                    jQuery('#hnumbererror').fadeOut();                                  
                });
                jQuery('#featuredImg').on('change', function() {
                    jQuery('#featImg').fadeOut();                                  
                });
                jQuery('#featured_image_api').on('change', function() {
                    jQuery('#imgapierror').fadeOut();                                  
                });
                jQuery('input[name="ot_taxonomy"]').on('change', function() {
                    jQuery('#caterror').fadeOut();                                  
                });
            
                jQuery('.generateBtn').on('click', function(e) {
                    e.preventDefault();
            
                    let title = '';
                    let selecttab = jQuery('.art-tabs').find('li.active').attr('data-tab');
            
                    if (selecttab == 'art-tab-1') {
                        title = jQuery('#prompt').val();
                        if (title === '') {
                            jQuery('#prompterror').text('Please enter the blog title');
                            return;
                        }
                    } else {
                        title = jQuery('#woprompt').val();
                        if (title === '') {
                            jQuery('#woprompterror').text('Please enter the blog title');
                            return;
                        }
                    }
            
                    let button = jQuery(this);
                    let tlanguage = jQuery('#tlanguage').val();
                    let twstyle = jQuery('#twstyle').val();
                    let writone = jQuery('#writone').val();
                    let countWord = jQuery('#countWord').val();
                    let subheading = jQuery("#subheading").is(':checked') ? "1" : "0";
                    let htaging = jQuery('#htaging').val();
                    let numberh = jQuery('#numberh').val();
                    let faqlist = jQuery("#faqlist").is(':checked') ? "1" : "0";
                    let featuredImg = jQuery('#featuredImg').val();
                    let seo_keyword = jQuery('#seo_keyword').val(); 
                    let metades = jQuery('#meta_description').val();
                    let imageAPi = jQuery('#featured_image_api').val();
            
                    /* console.log('Subheading:', subheading);
                    console.log('Heading tag:', htaging);
                    console.log('Number:', numberh);
                    console.log('Faq:', faqlist); */
                    
                    let category = jQuery('input[name="ot_taxonomy"]:checked').length;
            
                    if (countWord === '') { 
                        jQuery('#maxerror').text('Please enter the Max words');
                        return;
                    }
                    
                    // Only validate htaging and numberh if subheading is enabled
                    if (subheading === "1") {
                        if (htaging === '-- Select Header --' || htaging === null) {  
                            jQuery('#hgerror').text('Please select a valid H Group').fadeIn();
                            return;
                        }    
                        if (numberh === '-- Select Number --' || numberh === null) {  
                            jQuery('#hnumbererror').text('Please select Number').fadeIn();
                            return;
                        }
                    }
            
                    if (featuredImg === '-- None --' || featuredImg === null) {  
                        jQuery('#featImg').text('Please select Featured image').fadeIn();
                        return;
                    }
            
                    if (imageAPi === '') { 
                        jQuery('#imgapierror').text('Please enter image Api key');
                        return;
                    }
            
                    if (category === 0) { 
                        jQuery('#caterror').text('Please select at least one category').fadeIn();
                        return;
                    } 
                    
                    jQuery.ajax({
                        url: ajax_ob.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'otslf_ai_article_generate',
                            title: title,
                            seo_keyword: seo_keyword,
                            metades: metades,
                            tlanguage: tlanguage,
                            twstyle: twstyle,
                            writone: writone,
                            countWord: countWord,
                            subheading: subheading,
                            htaging: htaging,
                            numberh: numberh,
                            faqlist: faqlist,
                            imageAPi: imageAPi,
                            nonce: ajax_ob.nonce
                        },
                        beforeSend: function(xhr) {
                            console.log('button', button);
                            button.append('<span class="loading-spinner"></span>');
                        },
                        success: function(response) {
                            console.log(response);
                            if (response.success) { 
                                localStorage.setItem('successMessage', response.data.message);
                                $.toast({
                                    text: "Congratulations! Your blog post successfully generated",
                                    heading: 'success', 
                                    icon: 'success', 
                                    showHideTransition: 'fade', 
                                    allowToastClose: true, 
                                    hideAfter: 4000, 
                                    stack: 15, 
                                    position: { left: 'auto', right: 100, top: 153, bottom: 'auto' },
                                    textAlign: 'left',
                                    loader: true, 
                                    loaderBg: '#9EC600', 
                                });
                                setTimeout(function() {
                                    window.location.reload();
                                }, 2000);
                            } else {
                                jQuery.toast({ 
                                    text: response.data.message, 
                                    heading: 'Failed', 
                                    icon: 'error',
                                    showHideTransition: 'fade',
                                    allowToastClose: true, 
                                    hideAfter: 3000, 
                                    stack: 5, 
                                    position: 'top-right', 
                                });
                            }
                        },
                        complete: function() {
                            button.find('.loading-spinner').remove();
                        },
                        error: function(xhr, status, error) {
                            jQuery('#loading-spining').hide();
                            jQuery.toast({ 
                                text: 'Error code: ' + error.code, 
                                heading: 'Failed', 
                                icon: 'error',
                                showHideTransition: 'fade',
                                allowToastClose: true, 
                                hideAfter: 3000, 
                                stack: 5, 
                                position: 'top-right', 
                            });
                        }
                    });
                });
            
                var successMessage = localStorage.getItem('successMessage');
                if (successMessage) {
                    jQuery('#success-message').html('<div class="success-message">' + successMessage + '</div>');
                    localStorage.removeItem('successMessage');
                }
            });
            


            
                /*=============================== 
                        Prompt Delete 
                ================================= */  


            jQuery(document).ready(function($) {
                jQuery('#multidel').on('click', function() {
                    var button = jQuery(this);
                    var selectedProTitles = [];
                    jQuery('input[name="pro-title"]:checked').each(function() {
                        selectedProTitles.push(jQuery(this).val());
                    });
        
                    if (selectedProTitles.length > 0) {
                        jQuery.ajax({
                            url:ajax_ob.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'otslf_delete_selected_titles',
                                selected_pro_titles: selectedProTitles,
                                nonce: ajax_ob.nonce
                            },
                            beforeSend: function(xhr) {
                                button.append('<span class="loading-spinner"></span>');
                            },
                            success: function(response) {
                                if (response.success) {

                                    jQuery('input[name="pro-title"]:checked').closest('.accordion-item').remove();
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 300);
                                    jQuery.toast({
                                        text: "Selected titles deleted successfully!",
                                        heading: 'success', 
                                        icon: 'success', 
                                        showHideTransition: 'fade', 
                                        allowToastClose: true, 
                                        hideAfter: 2000, 
                                        stack: 15, 
                                        position: { left : 'auto', right : 100, top : 153, bottom : 'auto' },
                                        textAlign: 'left',
                                        loader: true, 
                                        loaderBg: '#9EC600', 
                                    });
                                    
                                } else {
                                    jQuery.toast({ text: 'Failed to delete selected titles. Try again.', 
                                        heading: 'Failed', 
                                        icon: 'error',
                                        showHideTransition: 'fade',
                                        allowToastClose: true, 
                                        hideAfter: 3000, 
                                        stack: 5, 
                                        position: 'top-right', 
                                    });
                                }
                            },
                            complete: function() {
                                button.find('.loading-spinner').remove();
                            },
                            error: function() {
                                jQuery.toast({ text: 'Error while deleting. Please try again.', 
                                    heading: 'Failed', 
                                    icon: 'error',
                                    showHideTransition: 'fade',
                                    allowToastClose: true, 
                                    hideAfter: 3000, 
                                    stack: 5, 
                                    position: 'top-right', 
                                });
                            }
                        });
                        
                    } else {
                        jQuery.toast({ text: 'Please select at least one title to delete.', 
                            heading: 'Failed', 
                            icon: 'error',
                            showHideTransition: 'fade',
                            allowToastClose: true, 
                            hideAfter: 3000, 
                            stack: 5, 
                            position: 'top-right', 
                        });
                    }
                });
            });
                

            /* === data store and display === */

            jQuery(document).ready(function () {
                jQuery('#titlelog').DataTable({
                        "bSort": false, 
                        language: {
                            search: "", // Custom label
                            searchPlaceholder: "Search here" // Custom placeholder
                        },
                        "info": true,  // Ensures that the info area is displayed
                        "infoCallback": function(settings, start, end, max, total, pre) {
                            return 'Total ' + total.toLocaleString() + ' Title';
                        }
                });
            });



        /* ====================================================================== 

                    Article Keyword and Meta descriipton generate 

           ======================================================================= */            


    
            jQuery('.section-checkbox').on('change', function() {
                jQuery('#multidel').show(); // This makes the button appear/disappear based on checkbox state
            });

            jQuery(document).ready(function($) {
                var titlekeywords = '';
                var titlemeta = '';
            
                // Click event for generating article and handling SEO data
                jQuery('.article-generate').on('click', function(e) {
                    e.preventDefault();
                    var button = jQuery(this);
                    var title = button.data('title');
                
                    jQuery.ajax({
                        url: ajax_ob.ajax_url,
                        method: 'POST',
                        dataType: 'JSON',
                        data: {
                            action: 'otslf_title_generate_seo_keyword',
                            blog_title: title,
                            nonce: ajax_ob.nonce
                        },
                        beforeSend: function(xhr) {
                            button.append('<span class="loading-spinner"></span>');
                        },
                        success: function(response) {
                            if (response.success) {
                                console.log('Keyword--:', response.data);
                                titlekeywords = response.data.join(', '); // Store the keyword
                                // Now trigger SEO Meta description generation only after keywords are retrieved
                                jQuery.ajax({
                                    url: ajax_ob.ajax_url,
                                    method: 'POST',
                                    dataType: 'JSON',
                                    data: {
                                        action: 'otslf_title_generate_meta_description',
                                        blog_title: title,
                                        nonce: ajax_ob.nonce
                                    },
                                    success: function(response) {
                                        if (response.success) {
                                            console.log('Meta--:', response.data);
                                            titlemeta = response.data; // Store the meta description
                                            // Now proceed with the main article generation process after both are fetched
                                            console.log('Article generation');
                                            
                                            generateArticle(button, title, titlekeywords, titlemeta);

                                            console.log('Title--:', title);
                                            console.log('Title Keywords--:', titlekeywords);
                                            console.log('Title Meta--:', titlemeta);
                                        }
                                    }
                                });
                            }
                        }
                    });
            });
                

                
                /* =================== GOOD SCRIPT ================= */


            function generateArticle(button, title, titlekeywords, titlemeta) {
                    console.log('In this site');
                        
                    console.log('Generate Key', titlekeywords);
                    console.log('Generate Meta', titlemeta);
            
                    jQuery('#loading-spinner').show();
                    jQuery.ajax({
                        url: ajax_ob.ajax_url,
                        type: 'POST',
                        dataType: 'JSON',
                        data: {
                            action: 'otslf_instant_generate_post_content',
                            title: title,
                            titlekeywords: titlekeywords, // Use the keyword
                            titlemeta: titlemeta,        // Use the meta description
                            nonce: ajax_ob.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Display success message via toaster
                                jQuery.toast({
                                    text: response.data.message,
                                    heading: 'Success',
                                    icon: 'success',
                                    showHideTransition: 'fade',
                                    allowToastClose: true,
                                    hideAfter: 3000,
                                    stack: 5,
                                    position: 'top-right'
                                });
            
                                localStorage.setItem('successMessage', response.data.message);
                                window.location.href = window.location.href;
                            } else {
                                jQuery.toast({
                                    text: response.data.message,
                                    heading: 'Failed',
                                    icon: 'error',
                                    showHideTransition: 'fade',
                                    allowToastClose: true,
                                    hideAfter: 3000,
                                    stack: 5,
                                    position: 'top-right'
                                });
                            }
                        },
                        complete: function() {
                            button.find('.loading-spinner').remove();
                        },
                        error: function(xhr, status, error) {
                            if (typeof error.code === 'undefined') {
                                jQuery.toast({
                                    text: "Please check your OpenAI key, it might be invalid",
                                    heading: 'Failed',
                                    icon: 'error',
                                    showHideTransition: 'fade',
                                    allowToastClose: true,
                                    hideAfter: 3000,
                                    stack: 5,
                                    position: 'top-right'
                                });
                            } else {
                                jQuery.toast({
                                    text: error.code,
                                    heading: 'Failed',
                                    icon: 'error',
                                    showHideTransition: 'fade',
                                    allowToastClose: true,
                                    hideAfter: 3000,
                                    stack: 5,
                                    position: 'top-right'
                                });
                            }
                        }
                    });
                }
            
                    // Display stored success message
                    let successMessage = localStorage.getItem('successMessage');
                    if (successMessage) {
                        jQuery('#status-message').html('<div class="success-message">' + successMessage + '</div>');
                        localStorage.removeItem('successMessage');
                } 

            }); 

        
            // Main article generation process after fetching keywords and meta

            /* =================== old SCRIPT ================= */


            /* function generateArticle(button, title, titlekeywords, titlemeta) {
                console.log('In this site');
                    
                console.log('Generate Key', titlekeywords);
                console.log('Generate Meta', titlemeta);
                //alert('ssss');
                
                jQuery('#loading-spinner').show();
                console.log('api call................:');

                jQuery.ajax({
                    url: ajax_ob.ajax_url,
                    type: 'POST',
                    dataType: 'JSON',
                    data: {
                        action: 'otslf_instant_generate_post_content',
                        title: title,
                        titlekeywords: titlekeywords, // Use the keyword
                        titlemeta: titlemeta,        // Use the meta description
                        nonce: ajax_ob.nonce
                    },
                    
                    success: function(response) {
                        console.log('reposne:',response);
                        
                        if (response.success) {

                            jQuery.toast({
                                text: response.data.message,
                                heading: 'Success',
                                icon: 'success',
                                showHideTransition: 'fade',
                                allowToastClose: true,
                                hideAfter: 3000,
                                stack: 5,
                                position: 'top-right'
                            });
        
                            localStorage.setItem('successMessage', response.data.message);

                            //location.reload(); 
                             
                            console.log('Success message');
                        } else {
                            jQuery.toast({
                                text: response.data.message,
                                heading: 'Failed',
                                icon: 'error',
                                showHideTransition: 'fade',
                                allowToastClose: true,
                                hideAfter: 3000,
                                stack: 5,
                                position: 'top-right'
                            });
                        }
                    },
                   
                    error: function(xhr, status, error) {
                        if (typeof error.code === 'undefined') {
                            jQuery.toast({
                                text: "Please check your OpenAI key, it might be invalid",
                                heading: 'Failed',
                                icon: 'error',
                                showHideTransition: 'fade',
                                allowToastClose: true,
                                hideAfter: 3000,
                                stack: 5,
                                position: 'top-right'
                            });
                        } else {
                            jQuery.toast({
                                text: error.code,
                                heading: 'Failed',
                                icon: 'error',
                                showHideTransition: 'fade',
                                allowToastClose: true,
                                hideAfter: 3000,
                                stack: 5,
                                position: 'top-right'
                            });
                        }
                    },
                    complete: function() {
                        console.log('complete:');

                        button.find('.loading-spinner').remove();
                    }

                });    
            }         
            
            let successMessage = localStorage.getItem('successMessage');
            if (successMessage) {
                jQuery('#status-message').html('<div class="success-message">' + successMessage + '</div>');
                localStorage.removeItem('successMessage');
            } */

               
           /* ==============================  
                      Delete Script 
            ===============================*/
            
            jQuery(document).ready(function($) {
                jQuery(document).on('click', '.delete-title', function(e) { 
                    e.preventDefault(); 
                    var button = jQuery(this);
                    var id = jQuery(this).data('title');
                    
                    var tooltipElement = jQuery(this).closest('div.tooltip');  // Select the tooltip div that contains the title and buttons
            
                    jQuery.ajax({
                        url: ajax_ob.ajax_url, 
                        type: 'POST',
                        dataType: 'json', 
                        data: {
                            action: 'otslf_delete_blog_title',
                            id: id,
                            nonce: ajax_ob.nonce
                        },
                        beforeSend: function(xhr) {
                            console.log('button', button);
                            button.append('<span class="loading-spinner"></span>');
                        },
                        success: function(response) {
                            if (response.success) {
                                tooltipElement.remove();  // Remove the tooltip div from the DOM
                                jQuery.toast({
                                    text: "Title deleted successfully",
                                    heading: 'success', 
                                    icon: 'success', 
                                    showHideTransition: 'fade', 
                                    allowToastClose: true, 
                                    hideAfter: 4000, 
                                    stack: 15, 
                                    position: { left : 'auto', right : 100, top : 153, bottom : 'auto' },
                                    textAlign: 'left',
                                    loader: true, 
                                    loaderBg: '#9EC600', 
                                });


                            } else {
                                jQuery.toast({ text: "Failed to delete title.", 
                                    heading: 'Failed', 
                                    icon: 'error',
                                    showHideTransition: 'fade',
                                    allowToastClose: true, 
                                    hideAfter: 3000, 
                                    stack: 5, 
                                    position: 'top-right', 
                                   });
                            }
                        },
                        complete: function() {
                            button.find('.loading-spinner').remove();
                        },
                        error: function() {
                            jQuery.toast({ text: "An error occurred while deleting the title.", 
                                heading: 'Failed', 
                                icon: 'error',
                                showHideTransition: 'fade',
                                allowToastClose: true, 
                                hideAfter: 3000, 
                                stack: 5, 
                                position: 'top-right', 
                            });
                        }
                    });
                });
            });
            
    

                /* ================================  
                         update title  Script 
                ===================================*/   

                    // Click event for the update-title button
                    jQuery('.update-title').on('click', function() {
                        var button = jQuery(this);
                        var tid = button.data('title');                      
                        var newTitle = button.closest('.tooltip').find('input[name="blog_titles"]').val(); 
                        
                        jQuery.ajax({
                            url: ajax_ob.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'otslf_update_blog_titles',
                                tid: tid,
                                new_title: newTitle,
                                nonce: ajax_ob.nonce
                            },
                            beforeSend: function(xhr) {
                                console.log('button', button);
                                button.append('<span class="loading-spinner"></span>');
                            },

                            success: function(response) {
                                if (response.success) {
                                    console.log(response);
                                    jQuery.toast({
                                        text: "Title updated successfully!",
                                        heading: 'success', 
                                        icon: 'success', 
                                        showHideTransition: 'fade', 
                                        allowToastClose: true, 
                                        hideAfter: 3000, 
                                        stack: 15, 
                                        position: { left : 'auto', right : 100, top : 153, bottom : 'auto' },
                                        textAlign: 'left',
                                        loader: true, 
                                        loaderBg: '#9EC600', 
                                    });

                                } else {
                                    console.log(response);
                                    jQuery.toast({ text: "Failed to update title.", 
                                        heading: 'Failed', 
                                        icon: 'error',
                                        showHideTransition: 'fade',
                                        allowToastClose: true, 
                                        hideAfter: 3000, 
                                        stack: 5, 
                                        position: 'top-right', 
                                    });
                                }
                            },
                            complete: function() {
                                button.find('.loading-spinner').remove();
                            },
                            error: function(xhr, status, error) {
                                console.log("Error: " + error);                        
                            }
                        });
                    });
                                

                    /* ====================================  
                          Multiple title Delete Script 
                    ======================================*/

            jQuery(document).ready(function($) {
                jQuery('#select_title').click(function() {
                    let isChecked = jQuery(this).is(':checked');
                    jQuery('input[name="select_title[]"]').prop('checked', isChecked);
                });          
                jQuery('#delete-selected').click(function(e) {
                    e.preventDefault();

                    let id = jQuery('input[name="select_title[]"]:checked').map(function() {
                        return jQuery(this).val();
                    }).get();
            
                    if (id.length === 0) {
                        jQuery.toast({ text: "Please select at least one title to delete.", 
                        heading: 'Failed', 
                        icon: 'error',
                        showHideTransition: 'fade',
                        allowToastClose: true, 
                        hideAfter: 3000, 
                        stack: 5, 
                        position: 'top-right', 
                       });                        
                    }
            
                    jQuery.ajax({
                        url: ajax_ob.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'slf_delete_multiple_blog_title',
                            id: id,
                            nonce: ajax_ob.nonce
                        },
                        beforeSend: function(xhr) {
                            jQuery('#delete-selected').append('<span class="loading-spinner"></span>');
                        },
                        success: function(response) {
                            if (response.success) {
                                jQuery('input[name="select_title[]"]:checked').closest('tr').remove();
                                jQuery.toast({
                                    text: "!Selected Title Deleted Successfully",
                                    heading: 'success', 
                                    icon: 'success', 
                                    showHideTransition: 'fade', 
                                    allowToastClose: true, 
                                    hideAfter: 4000, 
                                    stack: 15, 
                                    position: { left : 'auto', right : 100, top : 153, bottom : 'auto' },
                                    textAlign: 'left',
                                    loader: true, 
                                    loaderBg: '#9EC600', 
                                });
                                
                                setTimeout(function() {
                                    location.reload();
                                }, 2000); 

                            } else {
                                jQuery.toast({ text: "Please select at least one title to delete.", 
                                    heading: 'Failed', 
                                    icon: 'error',
                                    showHideTransition: 'fade',
                                    allowToastClose: true, 
                                    hideAfter: 3000, 
                                    stack: 5, 
                                    position: 'top-right', 
                                });
                            }
                        },
                      
                        complete: function() {
                            jQuery('#delete-selected').find('.loading-spinner').remove();
                        },

                        error: function(xhr, status, error) {
                            jQuery.toast({ text: 'An error occurred: ' + error, 
                                heading: 'NOTE', 
                                icon: 'error',
                                showHideTransition: 'fade',
                                allowToastClose: true, 
                                hideAfter: 3000, 
                                stack: 5, 
                                position: 'top-right', 
                            });
                        }
                    });
                });
            });
            
                
            
                    /*===========================  
                            vertical tab 
                    ========================== */

                    document.addEventListener('DOMContentLoaded', function() {
                        var tabs = document.querySelectorAll('.tab-links a');
                        tabs.forEach(function(tab) {
                        tab.addEventListener('click', function(e) {
                            e.preventDefault();
                            var activeTab = document.querySelector('.tab-links .active');
                            var activeContent = document.querySelector('.tab.active');
                            activeTab.classList.remove('active');
                            activeContent.classList.remove('active');
                            this.parentElement.classList.add('active');
                            document.querySelector(this.getAttribute('href')).classList.add('active');
                        });
                        });
                    });


                    /* ===============================
                            copy text  
                    =================================*/

                    jQuery(document).ready(function() {
                        jQuery('.copy-title').on('click', function() {
                            var title = jQuery(this).data('title');
                            var tempInput = jQuery('<input>');
                            jQuery('body').append(tempInput);
                            tempInput.val(title).select();
                            document.execCommand('copy');
                            tempInput.remove();
                    
                            // Display the toast message
                            showToast("Copied");
                        });
                    
                        // Function to show the toast message
                        function showToast(message) {
                            var toast = jQuery('<div class="toast-message"></div>').text(message);
                            jQuery('body').append(toast);
                            toast.fadeIn(400).delay(2000).fadeOut(400, function() {
                                jQuery(this).remove();
                            });
                        }
                    });
                    

                /*=============  generate title content  ==================*/

                jQuery("#tabs-nav li:first-child").addClass("active");
                jQuery(".tab-content").hide();
                jQuery(".tab-content:first").show();

                    // Click function
                    jQuery("#tabs-nav li").click(function() {
                        jQuery("#tabs-nav li").removeClass("active");
                        jQuery(this).addClass("active");
                        jQuery(".tab-content").hide();

                        var activeTab = jQuery(this).find("a").attr("href");
                        jQuery(activeTab).fadeIn();
                        return false;
                    });
                

                /* =============================== 
                       schecdule post 
                ========================== */
                
                jQuery(document).ready(function($) {
                    jQuery('input[name="schedule"]').change(function() {
                        if (jQuery('#hour').is(':checked')) {
                            jQuery('#sched_hour').show();
                        } else {
                            jQuery('#sched_hour').hide();
                        }
                    });

                    jQuery('input[name="schedule"]').change(function() {
                        if (jQuery('#daily').is(':checked')) {
                            jQuery('#sched_day').show();
                        } else {
                            jQuery('#sched_day').hide();
                        }
                    });
                });



                /* =============================== 
                       Charecter limit
                ========================== */

                jQuery(document).ready(function(){
                    var maxLength = 160;
                    
                    jQuery('#meta_description').keyup(function(){
                        var textLength = jQuery(this).val().length;            
                        if (textLength > maxLength) {
                            jQuery(this).val(jQuery(this).val().substring(0, maxLength));
                            jQuery('#limit_message').text('Character limit exceeded!').show();
                            setTimeout(function() {
                            jQuery('#limit_message').fadeOut('slow')
                          }, 5000);
                        }
                    });
                });


                 /* =============================== 
                       SEO keyword limit
                   ========================== */

                jQuery(document).ready(function(){
                    var maxKeywords = 5;         // Maximum number of keywords
                    var minWordsPerKeyword = 3;  // Minimum words per keyword
                    var maxWordsPerKeyword = 5;  // Maximum words per keyword
                
                    jQuery('#seo_keyword').on('input', function(){
                        var value = jQuery(this).val();
                        
                        // Split the input by commas and trim whitespace from each keyword
                        var keywords = value.split(',').map(function(keyword) {
                            return keyword.trim();
                        }).filter(function(keyword) {
                            return keyword.length > 0;  // Remove empty keywords
                        });
                
                        if (keywords.length > maxKeywords) {
                            keywords = keywords.slice(0, maxKeywords); 
                        }
                
                        var updatedKeywords = keywords.map(function(keyword) {
                            var words = keyword.split(/\s+/);  // Split by spaces to get words
                            if (words.length > maxWordsPerKeyword) {
                                words = words.slice(0, maxWordsPerKeyword);  // Truncate to max 5 words
                            }
                            return words.join(' ');  // Join the words back into a keyword
                        });
                
                        jQuery(this).val(updatedKeywords.join(', '));
                
                        var validKeywords = updatedKeywords.every(function(keyword) {
                            return keyword.split(/\s+/).length >= minWordsPerKeyword;
                        });
                
                        if (!validKeywords) {
                            jQuery('#keyword_limit').text('Each keyword must have at least 3 words').show();
                            setTimeout(function() {
                                jQuery('#keyword_limit').fadeOut('slow')
                            }, 10000);
                        }
                    });
                });
                
                
            
                 /*================================================
                   Article Generate Section Horizental Tab 
                ================================================= */

                jQuery(document).ready(function(){
                    // Click event for tabs
                    jQuery('.art-tab-link').click(function(){
                        var tab_id = jQuery(this).attr('data-tab');
                        jQuery('.art-tab-link').removeClass('active');
                        jQuery('.art-tab-content').removeClass('active');
                
                        jQuery(this).addClass('active');
                        jQuery("#" + tab_id).addClass('active');

                        if(tab_id === 'art-tab-2') {
                            jQuery('#generatetitle').hide(); // Hide the div
                        } 
                    });
                });



                /*=============================
                      Instant Article 
                ======================= */

                jQuery(document).ready(function($) {
                    jQuery('#instanttitle').on('click', function() {
                        var button = jQuery(this);
                        console.log('---999---');
                        var title = jQuery('#prompt').val();
                
                        if (title) {
                            jQuery('#loading-spinner').show();
                            jQuery.ajax({
                                url: ajax_ob.ajax_url,
                                method: 'POST',
                                dataType: 'JSON',
                                data: {
                                    action: 'otslf_instant_title_generate',
                                    title: title,
                                    nonce: ajax_ob.nonce
                                },
                                beforeSend: function() {
                                    console.log('button', button);
                                    button.append('<span class="loading-spinner"></span>');
                                },
                                success: function(response) {
                                    jQuery('#loading-spinner').hide();
                                    
                                    if (response.success) {
                                        console.log('---101---');
                                        console.log(response.success);

                                        if (!response.success || response.success === '0') {
                                            jQuery('#generatetitle').hide(); 
                                        } else {
                                            jQuery('#generatetitle').show(); 
                                        }

                                        jQuery('#ptitle').html(title);                                      
                                        var generatedContent = response.data.map(function(item) {
                                            return `
                                            <div class="tooltip">
                                                <li>
                                                    <input type="radio" class="select-title" name="select-title" data-title="${item}" />  
                                                    <input type="text" id="blog_titles" name="blog_titles" value="${item}">
                                                </li>
                                                <div class="tooltiptext">
                                                    <button type="button" class="update-title gtbutten" title="update" data-title="${item}">
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="m14.114 4.162-2.276-2.27a1.9 1.9 0 0 0-1.346-.559H3.239a1.91 1.91 0 0 0-1.906 1.906v9.522c0 1.05.855 1.906 1.906 1.906h9.529c1.05 0 1.899-.855 1.899-1.906V5.508a1.9 1.9 0 0 0-.553-1.346M10.808 2.62v1.596c0 .08-.067.148-.155.148h-5.3a.15.15 0 0 1-.154-.148v-1.67h5.293q.173.001.316.074m0 10.835h-5.63a.6.6 0 0 0 .02-.149V9.892c0-.08.068-.148.155-.148h5.3a.15.15 0 0 1 .155.148zm2.646-.694c0 .384-.31.694-.686.694h-.748V9.892c0-.754-.613-1.36-1.367-1.36h-5.3c-.754 0-1.367.606-1.367 1.36v3.414c0 .048.007.101.02.149H3.24a.693.693 0 0 1-.694-.694V3.239c0-.384.31-.693.694-.693h.747v1.67c0 .747.613 1.36 1.367 1.36h5.3c.754 0 1.367-.613 1.367-1.36v-.431l1.232 1.232c.135.128.202.31.202.491z" fill="#666"/>
                                                        </svg>
                                                    </button>
                                                    <button type="button" class="copy-title gtbutten" title="copy" data-title="${item}">
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M2.516 6.516c-.357.357-.583.976-.583 2.084v2.8c0 1.107.226 1.727.583 2.084s.977.583 2.084.583h2.8c1.108 0 1.727-.226 2.084-.583.358-.357.583-.977.583-2.084V8.6c0-1.108-.225-1.727-.583-2.084-.357-.358-.976-.583-2.084-.583H4.6c-1.107 0-1.727.225-2.084.583m-.849-.849c.693-.692 1.707-.934 2.933-.934h2.8c1.226 0 2.24.242 2.933.934s.934 1.707.934 2.933v2.8c0 1.226-.242 2.24-.934 2.932-.693.693-1.707.935-2.933.935H4.6c-1.226 0-2.24-.242-2.933-.934S.733 12.625.733 11.4V8.6c0-1.226.242-2.24.934-2.933" fill="#666"/>
                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M6.516 2.516c-.357.357-.583.976-.583 2.084v.133H7.4c1.226 0 2.24.242 2.933.934s.934 1.707.934 2.933v1.467h.133c1.108 0 1.727-.226 2.084-.583.358-.357.583-.977.583-2.084V4.6c0-1.108-.225-1.727-.583-2.084-.357-.358-.976-.583-2.084-.583H8.6c-1.107 0-1.727.225-2.084.583m-.849-.849C6.36.975 7.374.733 8.6.733h2.8c1.226 0 2.24.242 2.933.934s.934 1.707.934 2.933v2.8c0 1.226-.242 2.24-.934 2.932-.693.693-1.707.935-2.933.935h-.733a.6.6 0 0 1-.6-.6V8.6c0-1.108-.225-1.727-.583-2.084-.357-.358-.976-.583-2.084-.583H5.333a.6.6 0 0 1-.6-.6V4.6c0-1.226.242-2.24.934-2.933" fill="#666"/>
                                                        </svg>
                                                    </button>                              
                                                </div>
                                            </div>`;
                                        }).join('');
                                
                                        jQuery.toast({
                                            text: "Title Generated Successfully!",
                                            heading: 'Success',
                                            icon: 'success',
                                            showHideTransition: 'fade',
                                            allowToastClose: true,
                                            hideAfter: 4000,
                                            stack: 15,
                                            position: { left: 'auto', right: 100, top: 153, bottom: 'auto' },
                                            textAlign: 'left',
                                            loader: true,
                                            loaderBg: '#9EC600'
                                        });
                                        
                                        jQuery('#gtitle').html(generatedContent).show();

                                        //$('#generatedTitlesModal').fadeIn();
                                    } else {
                                        jQuery('#show-error').html('An error: ' + response.data).show();
                
                                        jQuery.toast({
                                            text: response.data,
                                            heading: 'Failed',
                                            icon: 'error',
                                            showHideTransition: 'fade',
                                            allowToastClose: true,
                                            hideAfter: 3000,
                                            stack: 5,
                                            position: 'top-right'
                                        });
                                    }
                                },
                                complete: function() {
                                    button.find('.loading-spinner').remove();
                                },
                                error: function() {
                                    jQuery('#loading-spinner').hide();
                                    /* $('#show-error').html('<p>An error occurred.</p>').show(); */
                                    jQuery.toast({
                                        text: 'An error occurred.',
                                        heading: 'Failed',
                                        icon: 'error',
                                        showHideTransition: 'fade',
                                        allowToastClose: true,
                                        hideAfter: 3000,
                                        stack: 5,
                                        position: 'top-right'
                                    });
                                }
                            });
                        } else {
                            jQuery.toast({
                                text: 'Please enter a blog post title',
                                heading: 'Failed',
                                icon: 'error',
                                showHideTransition: 'fade',
                                allowToastClose: true,
                                hideAfter: 3000,
                                stack: 5,
                                position: 'top-right'
                            });
                        }
                    });
                
                    jQuery(document).on('click', '.select-title', function() {
                        var selectedTitle = jQuery(this).data('title');
                        jQuery('#prompt').val(selectedTitle);
                    });
                });
                
                                
                /*================================================
                         Setting page Schedule Post
                ================================================= */
                
                (function($){
                    jQuery(document).ready(function () {
                // START TAB JS
                
                        //   Start Image Generation Accourdion
                        var acc = document.getElementsByClassName("accordion");
                        var i;
                
                        for (i = 0; i < acc.length; i++) {
                        acc[i].addEventListener("click", function () {
                            this.classList.toggle("active");
                            var panel = this.nextElementSibling;
                            if (panel.style.display === "block") {
                            panel.style.display = "none";
                            } else {
                            panel.style.display = "block";
                            }
                        });
                        }                        
                })
                })(jQuery);


                //   houres & minutes
                document.addEventListener("DOMContentLoaded", () => {
                    const hoursInput = document.getElementById("hoursInput");
                    const incrementButton = document.getElementById("increment");                        
                    const minutesInput = document.getElementById("minutesInput");
                    const incrementButton2 = document.getElementById("increment2");
                
                    if (incrementButton && hoursInput) {
                        incrementButton.addEventListener("click", () => {
                            let currentValue = parseInt(hoursInput.value);
                            if (currentValue < 23) {
                                hoursInput.value = currentValue + 1;
                            }
                        });
                    }
                
                    if (incrementButton2 && minutesInput) {
                        incrementButton2.addEventListener("click", () => {
                            let currentValue = parseInt(minutesInput.value);
                            if (currentValue < 59) {
                                minutesInput.value = currentValue + 1;
                            }
                        });
                    }
                });
                
            
            
            /* =============================== 
                    Log Delete
            ========================== */  

            jQuery(document).ready(function($) {
                jQuery(document).on('click', '.delbtn', function(e) {
                    e.preventDefault();
                    let id = jQuery(this).data('title');
                    let row = jQuery(this).closest('tr');
                    let confirmation = confirm("Are you sure you want to delete this title?");
                    
                    if (confirmation) {
                        jQuery.ajax({
                            url: ajax_ob.ajax_url,
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'slf_delete_log_title',
                                id: id,
                                nonce: ajax_ob.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    row.remove();
                                    jQuery.toast({
                                        text: "! Title Deleted Successfully",
                                        heading: 'success', 
                                        icon: 'success', 
                                        showHideTransition: 'fade', 
                                        allowToastClose: true, 
                                        hideAfter: 4000, 
                                        stack: 15, 
                                        position: { left : 'auto', right : 100, top : 153, bottom : 'auto' },
                                        textAlign: 'left',
                                        loader: true, 
                                        loaderBg: '#9EC600', 
                                    });
                                } else {
                                   
                                    jQuery.toast({
                                        heading: 'Error',
                                        text: 'Failed to delete title.',
                                        showHideTransition: 'fade',
                                        icon: 'error',
                                        position: 'top-right',
                                        hideAfter: 4000
                                    });
                                }
                            },
                            error: function(jqXHR, textStatus, errorThrown) {
                                console.error('AJAX Error:', textStatus, errorThrown);
                                console.log('Response Text:', jqXHR.responseText);
                                jQuery.toast({
                                    heading: 'Error',
                                    text: 'There was an issue deleting the title. Please try again.',
                                    showHideTransition: 'fade',
                                    icon: 'error',
                                    position: 'top-right',
                                    hideAfter: 4000
                                });
                            }
                        });
                    } else {
                        console.log("Delete operation canceled.");
                    }
                });
            });
            

    

            /* =============================== 
                  SEO Keyword Generate
               ========================== */ 

            jQuery(document).ready(function($) {
                jQuery('.seokeyword').on('click', function() {
            
                    let title = '';
                    let selecttab = jQuery('.art-tabs').find('li.active').attr('data-tab');
                    
                    if (selecttab == 'art-tab-1') {
                        title = jQuery('#prompt').val();
            
                        jQuery('#prompt').change('input', function() {
                            jQuery('#prompterror').fadeOut();
                        });
            
                        if (title === '') {
                            jQuery('#prompterror').text('Please enter the blog title');
                            return;
                        }
            
                    } else {
            
                        title = jQuery('#woprompt').val();
            
                        jQuery('#woprompt').change('input', function() {
                            jQuery('#woprompterror').fadeOut();
                        });
            
                        if (title === '') {
                            jQuery('#woprompterror').text('Please enter the blog title');
                            return;
                        }
                    }
            
                    console.log(title);
                    let button = jQuery(this);
            
                    if (title) {
                        
                        jQuery('#loading-spinner').show();
                        jQuery.ajax({
                            url: ajax_ob.ajax_url,
                            method: 'POST',
                            dataType: 'JSON',
                            data: {
                                action: 'otslf_generate_seo_keyword',
                                blog_title: title,
                                nonce: ajax_ob.nonce
                            },
            
                            beforeSend: function(xhr) {
                                console.log('button', button);
                                button.append('<span class="loading-spinner"></span>');
                            },
            
                            success: function(response) {
                                if (response.success) {
                                    console.log(response.data);
                                    const keywords = response.data.join(', ');
                                    jQuery('#seo_keyword').val(keywords);

                                    jQuery.toast({
                                        text: "Keyword & Meta Description Successfully Generated",
                                        heading: 'success', 
                                        icon: 'success', 
                                        showHideTransition: 'fade', 
                                        allowToastClose: true, 
                                        hideAfter: 3000, 
                                        stack: 15, 
                                        position: { left : 'auto', right : 100, top : 153, bottom : 'auto' },
                                        textAlign: 'left',
                                        loader: true, 
                                        loaderBg: '#9EC600', 
                                    });

            
                                } else {
                                    jQuery.toast({ text: 'An error: ' + response.data, 
                                        heading: 'Failed', 
                                        icon: 'error',
                                        showHideTransition: 'fade',
                                        allowToastClose: true, 
                                        hideAfter: 3000, 
                                        stack: 5, 
                                        position: 'top-right', 
                                    });
                                }
                            },
                            complete: function() {
                                button.find('.loading-spinner').remove();
                            },
                            error: function(error) {
                                jQuery.toast({ text: 'An error occurred', 
                                    heading: 'Failed', 
                                    icon: 'error',
                                    showHideTransition: 'fade',
                                    allowToastClose: true, 
                                    hideAfter: 3000, 
                                    stack: 5, 
                                    position: 'top-right', 
                                });
                            }
                        });
                    } else {
                        jQuery.toast({ text: 'Please enter a blog post title', 
                            heading: 'Failed', 
                            icon: 'error',
                            showHideTransition: 'fade',
                            allowToastClose: true, 
                            hideAfter: 3000, 
                            stack: 5, 
                            position: 'top-right', 
                        });
                    }
                });
            });


            /* =================================== 
                        Meta description
               ================================== */ 

            jQuery(document).ready(function($) {
                jQuery('.seokeyword').on('click', function() {
            
                    let title = '';
                    let selecttab = jQuery('.art-tabs').find('li.active').attr('data-tab');
            
                    if (selecttab == 'art-tab-1') {
                        title = jQuery('#prompt').val();
            
                        jQuery('#prompt').change('input', function() {
                            jQuery('#prompterror').fadeOut();
                        });
            
                        if (title === '') {
                            jQuery('#prompterror').text('Please enter the blog title');
                            return;
                        }

                    } else {
                        title = jQuery('#woprompt').val();
                        jQuery('#woprompt').change('input', function() {
                            jQuery('#woprompterror').fadeOut();
                        });
            
                        if (title === '') {
                            jQuery('#woprompterror').text('Please enter the blog title');
                            return;
                        }
                    }
            
                    if (title) {
            
                        jQuery('#loading-spinner').show();
                        jQuery.ajax({
                            url: ajax_ob.ajax_url,
                            method: 'POST',
                            dataType: 'JSON',
                            data: {
                                action: 'otslf_generate_seo_metadescription',
                                blog_title: title,
                                nonce: ajax_ob.nonce
                            },
            
                            success: function(response) {
                                if (response.success) {
                                    console.log(response.data);
            
                                    const meta = response.data;
                                    jQuery('#meta_description').val(meta);
                                } else {
                                    
                                    jQuery.toast({ text: 'An error: ' + response.data, 
                                        heading: 'Failed', 
                                        icon: 'error',
                                        showHideTransition: 'fade',
                                        allowToastClose: true, 
                                        hideAfter: 3000, 
                                        stack: 5, 
                                        position: 'top-right', 
                                       });
                                }
                            },
                            error: function(error) {
                                jQuery.toast({ text: 'An error occurred.', 
                                    heading: 'Failed', 
                                    icon: 'error',
                                    showHideTransition: 'fade',
                                    allowToastClose: true, 
                                    hideAfter: 3000, 
                                    stack: 5, 
                                    position: 'top-right', 
                                   });
                            }
                        });
                    } else {
                        jQuery('#show-error').html('Please enter a blog post title').show();
                    }
                });
            });

            
            /* =============================== 
                  Schedule post maintain
               ========================== */ 
               
            jQuery(document).ready(function($) {
                function toggleScheduleSections() {
                    var selectedValue = $('input[name="otslf_schedule"]:checked').val();
                    
                    jQuery('#sameday, #later, #recurring').hide(); // Hide all sections
            
                    if (selectedValue === 'sameday') {
                        jQuery('#sameday').show();
                    } else if (selectedValue === 'later') {
                        jQuery('#later').show();
                    } else if (selectedValue === 'recurring') {
                        jQuery('#recurring').show();
                    }
                }

                toggleScheduleSections();
    
                jQuery(document).on('change', 'input[name="otslf_schedule"]', function() {
                    toggleScheduleSections();
                });
            
                // Handling same-day schedule options
                function toggleSameDayOptions() {
                    var sameday_schedule = $('input[name="otslf_same_schedule"]:checked').val();
                    if (sameday_schedule === 'later_same_day') {
                        jQuery('.same_schedule_post').show();
                    } else {
                        jQuery('.same_schedule_post').hide();
                    }
                }
                toggleSameDayOptions();
        
                jQuery(document).on('change', 'input[name="otslf_same_schedule"]', function() {
                    toggleSameDayOptions();
                });
            });
              

            /* =============================== 
                  Featured Image select
               ========================== */
                               
            jQuery(document).ready(function($) {
                 
                 jQuery('#otslf_dall3_generate_api_key').hide();
                 jQuery('#otslf_image_generate_api_key').hide();
                 jQuery('#otslf_unsplash_generate_api_key').hide();
                 
                function toggleImageGenerate() {
                    var selectedValue = jQuery('#otslf_featured_image').val();
                    if (selectedValue === 'pixabay') {
                        jQuery('#otslf_image_generate_api_key').show();
                        jQuery('#otslf_dall3_generate_api_key').hide();
                        jQuery('#otslf_unsplash_generate_api_key').hide();
                    }else if (selectedValue === 'dalle3') {
                        jQuery('#otslf_dall3_generate_api_key').show();
                        jQuery('#otslf_image_generate_api_key').hide();
                        jQuery('#otslf_unsplash_generate_api_key').hide();
                    }else if (selectedValue === 'unsplash') {
                        jQuery('#otslf_unsplash_generate_api_key').show();
                        jQuery('#otslf_dall3_generate_api_key').hide();
                        jQuery('#otslf_image_generate_api_key').hide();                        
                    }
                }
                jQuery('#otslf_featured_image').on('change', toggleImageGenerate);
                toggleImageGenerate(); // Call on page load to set initial state
            });  
    
            
                /* ================================ 
                         Api key open & Close
                =================================*/

                jQuery(".api_input_field").click(function() {
                    jQuery(this).toggleClass("show-api_input_field");
                    var input = jQuery(jQuery(this).attr("toggle"));
                    if (input.attr("type") == "password") {
                      input.attr("type", "text");
                    } else {
                      input.attr("type", "password");
                    }
                });
                
                
                jQuery(".img_api_input_field").click(function() {
                    jQuery(this).toggleClass("show-img_api_input_field");
                    

                    var input = jQuery(this).prev("input");
                
                    if (input.attr("type") === "password") {
                        input.attr("type", "text");
                    } else {
                        input.attr("type", "password");
                    }
                });
                

                jQuery(".dall3_api_input_field").click(function() {
                    jQuery(this).toggleClass("show-dall3_api_input_field");
                    var input = jQuery(this).prev("input");
                    if (input.attr("type") == "password") {
                        input.attr("type", "text");
                    } else {
                        input.attr("type", "password");
                    }                    
                });   

                jQuery(".dall3hd_api_input_field").click(function() {
                    jQuery(this).toggleClass("show-dall3hd_api_input_field");        
                    var input = jQuery(this).prev("input");
                    if (input.attr("type") == "password") {
                        input.attr("type", "text");
                    } else {
                        input.attr("type", "password");
                    }                    
                });

         


                /*=============================================== 
                            button Enable or disable  
                ===============================================*/
                
                    
                jQuery(document).ready(function() {

                        jQuery('#blog_title').on('input', function() {
                            jQuery('#generate_button').removeClass('disabled').addClass('enabled').prop('disabled', false);
                          });
                                                   
                        jQuery('#prompt').on('input', function() {
                            jQuery('#instanttitle').removeClass('atdisabled').addClass('enabled').prop('atdisabled', false);          
                           });   

                        jQuery('#woprompt').on('input', function() {
                            jQuery('.generateBtn').removeClass('disabled').addClass('enabled').prop('disabled', false);             
                         });
                        
                        jQuery('#prompt').on('input', function() {
                            jQuery('.generateBtn').removeClass('disabled').addClass('enabled').prop('disabled', false);             
                        }); 
                });




               /*=============================================== 
                        Setting success Message Show
                ===============================================*/


                jQuery(document).ready(function($) {
                // Check if the success message exists
                if (jQuery('#setting-success-message').length) {
                    jQuery.toast({
                        heading: 'Success',
                        text: jQuery('#setting-success-message').text(),
                        showHideTransition: 'slide',
                        icon: 'success',
                        position: 'top-right',
                        hideAfter: 3000,
                        class: 'setting_toast_sms',
                    });
                }
            });


            /*=============================================== 
                    number input validation
            ===============================================*/

            jQuery(document).ready(function(){
                jQuery('input[type="number"]').on('input', function() {
                  let inputValue = jQuery(this).val();                  
                
                  let numericValue = Number(inputValue);
                  
                  // Check if conversion resulted in NaN (Not a Number)
                  if(isNaN(numericValue) || inputValue.trim() === ""){
                    jQuery('#warningsms').text('Invalid input: Please enter a valid number.');
                    // You could also add custom error handling here
                  } else {
                    console.log("Valid number:", numericValue);
                  }
                });
              });