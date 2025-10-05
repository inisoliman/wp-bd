jQuery(document).ready(function($) {
    'use strict';
    
    // تحسين معالجة الأحداث للمحتوى المحمل ديناميكياً
    
    /**
     * إعادة تهيئة التحكمات بعد تحميل محتوى جديد
     */
    function reinitializeBibleControls($container) {
        if (!$container || !$container.length) {
            return;
        }
        
        // إعادة تخزين HTML الأصلي للآيات
        cacheOriginalVerseHTML($container);
        
        // إعادة تهيئة خيارات الصور إذا كانت موجودة
        populateImageOptionSelects($container);
        
        // إعادة تطبيق الوضع الليلي إذا كان مفعلاً
        applyDarkModeToNewContent($container);
        
        // إعادة تهيئة أزرار القراءة الصوتية
        initializeReadAloudButtons($container);
        
        console.log('Bible controls reinitialized for new content');
    }
    
    /**
     * تخزين HTML الأصلي للآيات
     */
    function cacheOriginalVerseHTML($container) {
        $container.find('.verse-text').each(function() {
            const $verseP = $(this);
            const $textContent = $verseP.find('.text-content');
            if ($textContent.length && !$verseP.data('original-html')) {
                $verseP.data('original-html', $textContent.html());
            }
        });
    }
    
    /**
     * إعادة تعبئة خيارات الصور
     */
    function populateImageOptionSelects($container) {
        const IMAGE_FONTS_DATA = (typeof bibleFrontend !== 'undefined' && bibleFrontend.image_fonts_data) ? bibleFrontend.image_fonts_data : {};
        const IMAGE_BACKGROUNDS_DATA = (typeof bibleFrontend !== 'undefined' && bibleFrontend.image_backgrounds_data) ? bibleFrontend.image_backgrounds_data : {};
        
        $container.find('select[id^="bible-image-font-select"]').each(function() {
            const $select = $(this);
            if ($select.children('option').length <= 1) {
                $select.empty();
                $.each(IMAGE_FONTS_DATA, function(key, font) {
                    $select.append($('<option>', { value: key, text: font.label }));
                });
                if ($select.val() === null && Object.keys(IMAGE_FONTS_DATA).length > 0) {
                    $select.val(Object.keys(IMAGE_FONTS_DATA)[0]);
                }
            }
        });
        
        $container.find('select[id^="bible-image-bg-select"]').each(function() {
            const $select = $(this);
            if ($select.children('option').length <= 1) {
                $select.empty();
                $.each(IMAGE_BACKGROUNDS_DATA, function(key, bg) {
                    $select.append($('<option>', { value: key, text: bg.label }));
                });
                if ($select.val() === null && Object.keys(IMAGE_BACKGROUNDS_DATA).length > 0) {
                    $select.val(Object.keys(IMAGE_BACKGROUNDS_DATA)[0]);
                }
            }
        });
    }
    
    /**
     * تطبيق الوضع الليلي على المحتوى الجديد
     */
    function applyDarkModeToNewContent($container) {
        const isDarkMode = $('body').hasClass('dark-mode');
        
        if (isDarkMode) {
            $container.find('.dark-mode-toggle-button').each(function() {
                const $button = $(this);
                $button.find('.label').text('الوضع النهاري');
                $button.find('i').removeClass('fa-moon').addClass('fa-sun');
            });
        } else {
            $container.find('.dark-mode-toggle-button').each(function() {
                const $button = $(this);
                $button.find('.label').text('الوضع الليلي');
                $button.find('i').removeClass('fa-sun').addClass('fa-moon');
            });
        }
    }
    
    /**
     * تهيئة أزرار القراءة الصوتية للمحتوى الجديد
     */
    function initializeReadAloudButtons($container) {
        $container.find('.read-aloud-button').each(function() {
            const $button = $(this);
            // إعادة تعيين حالة الزر
            $button.find('.label').text('قراءة بصوت عالٍ');
            $button.find('i').removeClass('fa-stop-circle').addClass('fa-volume-up');
        });
    }
    
    /**
     * معالج أحداث محسن للمحتوى الديناميكي
     */
    $(document).on('DOMNodeInserted', function(e) {
        const $target = $(e.target);
        
        // التحقق من أن العنصر المضاف يحتوي على محتوى كتابي
        if ($target.hasClass('bible-content-area') || 
            $target.find('.bible-content-area').length > 0 ||
            $target.hasClass('verses-text-container') ||
            $target.find('.verses-text-container').length > 0) {
            
            // إعادة تهيئة التحكمات بعد فترة قصيرة للتأكد من اكتمال التحميل
            setTimeout(function() {
                reinitializeBibleControls($target);
            }, 100);
        }
    });
    
    /**
     * معالج بديل للمتصفحات الحديثة باستخدام MutationObserver
     */
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    Array.from(mutation.addedNodes).forEach(function(node) {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            const $node = $(node);
                            
                            // التحقق من المحتوى الكتابي
                            if ($node.hasClass('bible-content-area') || 
                                $node.find('.bible-content-area').length > 0 ||
                                $node.hasClass('verses-text-container') ||
                                $node.find('.verses-text-container').length > 0 ||
                                $node.hasClass('verse-text') ||
                                $node.find('.verse-text').length > 0) {
                                
                                setTimeout(function() {
                                    reinitializeBibleControls($node);
                                }, 50);
                            }
                        }
                    });
                }
            });
        });
        
        // مراقبة التغييرات في الجسم الرئيسي للصفحة
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        console.log('MutationObserver initialized for Bible content');
    }
    
    /**
     * معالج خاص لتحديث المحتوى عبر AJAX
     */
    $(document).ajaxComplete(function(event, xhr, settings) {
        // التحقق من أن الطلب متعلق بالكتاب المقدس
        if (settings.data && typeof settings.data === 'string') {
            if (settings.data.indexOf('bible_get_verses') !== -1 ||
                settings.data.indexOf('bible_get_chapters') !== -1 ||
                settings.data.indexOf('bible_get_books_by_testament') !== -1) {
                
                // إعادة تهيئة التحكمات بعد فترة قصيرة
                setTimeout(function() {
                    const $bibleContainer = $('#bible-container, .bible-content-area').first();
                    if ($bibleContainer.length) {
                        reinitializeBibleControls($bibleContainer);
                    }
                }, 200);
            }
        }
    });
    
    /**
     * تحسين أداء معالجة الأحداث باستخدام debounce
     */
    function debounce(func, wait, immediate) {
        let timeout;
        return function executedFunction() {
            const context = this;
            const args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }
    
    /**
     * معالج محسن للنقر على روابط الكلمات
     */
    $(document).on('click', '.bible-term', debounce(function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $term = $(this);
        const termText = $term.text().trim();
        const termDefinition = $term.data('definition');
        
        if (termText && termDefinition) {
            // إظهار modal التعريف
            const $modal = $('#definition-modal');
            const $modalTerm = $('#modal-term');
            const $modalDefinition = $('#modal-definition');
            
            if ($modal.length && $modalTerm.length && $modalDefinition.length) {
                $modalTerm.text(termText);
                $modalDefinition.html(termDefinition);
                $modal.addClass('active');
                
                // إضافة تأثير بصري
                $term.addClass('term-clicked');
                setTimeout(function() {
                    $term.removeClass('term-clicked');
                }, 300);
            }
        }
    }, 250));
    
    /**
     * تحسين الاستجابة لتغيير حجم النافذة
     */
    $(window).on('resize', debounce(function() {
        // إعادة حساب أحجام العناصر إذا لزم الأمر
        $('.bible-modal-content').each(function() {
            const $modal = $(this);
            // يمكن إضافة logic لإعادة تحديد الحجم
        });
    }, 250));
    
    // إضافة CSS للتأثيرات البصرية
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .bible-term.term-clicked {
                background-color: #3b82f6;
                color: white;
                transform: scale(1.05);
                transition: all 0.2s ease;
            }
            
            .bible-content-area {
                transition: opacity 0.3s ease;
            }
            
            .bible-content-area.loading {
                opacity: 0.7;
                pointer-events: none;
            }
            
            .verse-text {
                transition: background-color 0.2s ease;
            }
            
            .verse-text:hover {
                background-color: rgba(59, 130, 246, 0.05);
            }
        `)
        .appendTo('head');
    
    console.log('Bible dynamic content handler initialized');
});