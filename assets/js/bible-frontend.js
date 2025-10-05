jQuery(document).ready(function($) {
    // --- ابدأ: تهيئة المتغيرات العامة ---
    const BIBLE_STRINGS = (typeof bibleFrontend !== 'undefined' && bibleFrontend.localized_strings) ? bibleFrontend.localized_strings : {};
    const IMAGE_GENERATOR_STRINGS = (typeof bibleFrontend !== 'undefined' && bibleFrontend.image_generator) ? bibleFrontend.image_generator : {};
    const AJAX_URL = (typeof bibleFrontend !== 'undefined' && bibleFrontend.ajax_url) ? bibleFrontend.ajax_url : '/wp-admin/admin-ajax.php';
    const AJAX_NONCE = (typeof bibleFrontend !== 'undefined' && bibleFrontend.nonce) ? bibleFrontend.nonce : '';
    const BASE_URL = (typeof bibleFrontend !== 'undefined' && bibleFrontend.base_url) ? bibleFrontend.base_url : '/bible/';
    const MY_BIBLE_PLUGIN_URL_JS = (typeof bibleFrontend !== 'undefined' && bibleFrontend.plugin_url) ? bibleFrontend.plugin_url : '';

    const DEFAULT_DARK_MODE = (typeof bibleFrontend !== 'undefined' && bibleFrontend.default_dark_mode) ? bibleFrontend.default_dark_mode : false;
    const DEFAULT_TESTAMENT_VIEW = (typeof bibleFrontend !== 'undefined' && bibleFrontend.default_testament_view) ? bibleFrontend.default_testament_view : 'all';
    const TESTAMENTS_LABELS_FROM_PHP = (typeof bibleFrontend !== 'undefined' && bibleFrontend.testaments) ? bibleFrontend.testaments : {all: BIBLE_STRINGS.all || 'الكل'};
    
    const IMAGE_FONTS_DATA = (typeof bibleFrontend !== 'undefined' && bibleFrontend.image_fonts_data) ? bibleFrontend.image_fonts_data : {};
    const IMAGE_BACKGROUNDS_DATA = (typeof bibleFrontend !== 'undefined' && bibleFrontend.image_backgrounds_data) ? bibleFrontend.image_backgrounds_data : {};

    const $bibleContentContainer = $('#bible-container');
    let $testamentSelect, $bookSelect, $chapterSelect, $versesDisplay, $mainPageTitleElement;

    // --- Dictionary Modal Elements ---
    const $definitionModal = $('#definition-modal');
    const $modalTerm = $('#modal-term');
    const $modalDefinition = $('#modal-definition');

    const $chapterTermsModal = $('#chapter-terms-modal');
    const $chapterTermsList = $('#chapter-terms-list');

    // *** MODIFIED: This function now accepts a container to cache ***
    function cacheOriginalVerseHTML(container) {
        if (!container || !container.length) return;
        container.find('.verse-text').each(function() {
            const $verseP = $(this);
            if (!$verseP.data('original-html')) {
                $verseP.data('original-html', $verseP.find('.text-content').html());
            }
        });
    }

    // *** MODIFIED: This function is now more robust ***
    function initializeSelectors() {
        // Find main page elements if they exist
        if ($bibleContentContainer.length) {
            $testamentSelect = $bibleContentContainer.find('#bible-testament-select');
            $bookSelect = $bibleContentContainer.find('#bible-book-select');
            $chapterSelect = $bibleContentContainer.find('#bible-chapter-select');
            $versesDisplay = $bibleContentContainer.find('#bible-verses-display');
        }
        $mainPageTitleElement = $('h1#bible-main-page-title').first();

        // ** THE FIX IS HERE **
        // Find ANY bible content area on the page, not just #bible-container
        const $anyContentArea = $('.bible-content-area, .bible-single-verse-container, .random-verse-widget, .daily-verse-widget').first();
        if ($anyContentArea.length) {
            // Cache the initial HTML for the found container
            cacheOriginalVerseHTML($anyContentArea);
        }

        populateImageOptionSelects();
    }
    
    function populateImageOptionSelects() {
        $('select[id^="bible-image-font-select"]').each(function() {
            const $select = $(this);
            if ($select.children('option').length <= 1) {
                $select.empty();
                $.each(IMAGE_FONTS_DATA, function(key, font) {
                    $select.append($('<option>', { value: key, text: font.label }));
                });
                if($select.val() === null && Object.keys(IMAGE_FONTS_DATA).length > 0){
                    $select.val(Object.keys(IMAGE_FONTS_DATA)[0]);
                }
            }
        });
        $('select[id^="bible-image-bg-select"]').each(function() {
            const $select = $(this);
            if ($select.children('option').length <= 1) {
                $select.empty();
                $.each(IMAGE_BACKGROUNDS_DATA, function(key, bg) {
                    $select.append($('<option>', { value: key, text: bg.label }));
                });
                if($select.val() === null && Object.keys(IMAGE_BACKGROUNDS_DATA).length > 0){
                    $select.val(Object.keys(IMAGE_BACKGROUNDS_DATA)[0]);
                }
            }
        });
    }

    initializeSelectors();

    let currentUtterance = null;
    let isReading = false;

    function removeArabicTashkeel(text) {
        if (typeof text !== 'string') return '';
        // إزالة شاملة لجميع رموز التشكيل العربية
        return text.replace(/[\u064B-\u065F\u0670\u06D6-\u06ED\u08D4-\u08E1\u08E3-\u08FF\u0617-\u061A]/g, '');
    }
    
    function setTashkeelState(container, showTashkeel) {
        container.find('.verse-text').each(function() {
            const $verseP = $(this);
            const $textContent = $verseP.find('.text-content');
            
            if (showTashkeel) {
                const originalHTML = $verseP.data('original-html');
                if (originalHTML) {
                    $textContent.html(originalHTML);
                }
            } else {
                $textContent.contents().each(function() {
                    if (this.nodeType === 3) { // Node.TEXT_NODE
                        this.nodeValue = removeArabicTashkeel(this.nodeValue);
                    }
                });
            }
        });
    }

    function applyDarkModePreference() {
        const isDarkMode = localStorage.getItem('darkMode') === 'enabled' || (localStorage.getItem('darkMode') === null && DEFAULT_DARK_MODE);
        const $body = $('body');
        const $toggleButton = $('.dark-mode-toggle-button');
        if (isDarkMode) {
            $body.addClass('dark-mode');
            $toggleButton.find('.label').text('الوضع النهاري');
            $toggleButton.find('i').removeClass('fa-moon').addClass('fa-sun');
        } else {
            $body.removeClass('dark-mode');
            $toggleButton.find('.label').text('الوضع الليلي');
            $toggleButton.find('i').removeClass('fa-sun').addClass('fa-moon');
        }
    }

    function toggleDarkMode() {
        if ($('body').hasClass('dark-mode')) { localStorage.setItem('darkMode', 'disabled'); } 
        else { localStorage.setItem('darkMode', 'enabled'); }
        applyDarkModePreference();
    }

    function getArabicVoice() {
        if (typeof speechSynthesis === 'undefined') return null;
        const voices = window.speechSynthesis.getVoices();
        let arabicVoice = voices.find(voice => voice.lang.toLowerCase().startsWith('ar'));
        if (!arabicVoice) arabicVoice = voices.find(voice => voice.name.toLowerCase().includes('arabic'));
        return arabicVoice;
    }

    if (typeof speechSynthesis !== 'undefined' && speechSynthesis.onvoiceschanged !== undefined) {
        speechSynthesis.onvoiceschanged = getArabicVoice;
    }

    function handleReadAloud($contentArea, $button) {
        if (typeof speechSynthesis === 'undefined' || !('speak' in speechSynthesis)) {
            alert('عذراً، متصفحك لا يدعم ميزة القراءة الصوتية.'); return;
        }
        if (isReading) {
            speechSynthesis.cancel(); isReading = false;
            $button.find('.label').text('قراءة بصوت عالٍ');
            $button.find('i').removeClass('fa-stop-circle').addClass('fa-volume-up'); return;
        }
        let textToRead = '';
        const $textElements = $contentArea.find('.verses-text-container .verse-text .text-content');
        if ($textElements.length > 0) {
            $textElements.each(function() { textToRead += $(this).text().trim() + ' '; });
        } else {
            const $singleSourceText = $contentArea.find('.random-verse .text-content, .daily-verse .text-content, .search-result-item .text-content, .verse-text .text-content');
            if ($singleSourceText.length > 0) {
                 textToRead = $singleSourceText.map(function() { return $(this).text().trim(); }).get().join(' ');
            }
        }
        if (textToRead.trim() === '') { alert('لا يوجد نص للقراءة.'); return; }
        currentUtterance = new SpeechSynthesisUtterance(textToRead.trim());
        const arabicVoice = getArabicVoice();
        if (arabicVoice) { currentUtterance.voice = arabicVoice; } 
        else { console.warn("لم يتم العثور على صوت عربي."); currentUtterance.lang = 'ar-SA'; }
        currentUtterance.pitch = 1; currentUtterance.rate = 0.9;
        currentUtterance.onstart = () => {
            isReading = true;
            $button.find('.label').text('إيقاف القراءة');
            $button.find('i').removeClass('fa-volume-up').addClass('fa-stop-circle');
        };
        currentUtterance.onend = () => {
            isReading = false;
            $button.find('.label').text('قراءة بصوت عالٍ');
            $button.find('i').removeClass('fa-stop-circle').addClass('fa-volume-up');
            currentUtterance = null;
        };
        currentUtterance.onerror = (event) => {
            console.error('SpeechSynthesisUtterance.onerror', event);
            alert('حدث خطأ أثناء محاولة القراءة: ' + event.error);
            isReading = false;
            $button.find('.label').text('قراءة بصوت عالٍ');
            $button.find('i').removeClass('fa-stop-circle').addClass('fa-volume-up');
        };
        speechSynthesis.speak(currentUtterance);
    }

    function generateVerseImage(verseText, verseReference, $imageContainer, selectedFontKey, selectedBgKey) {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (!ctx) { alert('Canvas not supported'); return; }

        const canvasWidth = 800, canvasHeight = 500;
        canvas.width = canvasWidth; canvas.height = canvasHeight;

        const backgroundOption = (IMAGE_BACKGROUNDS_DATA && IMAGE_BACKGROUNDS_DATA[selectedBgKey]) || { type: 'gradient', colors: ['#4B0082', '#00008B', '#2F4F4F'], textColor: '#FFFFFF' };
        let textColor = backgroundOption.textColor || '#FFFFFF';

        ctx.clearRect(0, 0, canvasWidth, canvasHeight);

        if (backgroundOption.type === 'gradient') {
            const gradient = ctx.createLinearGradient(0, 0, 0, canvasHeight);
            const step = backgroundOption.colors.length > 1 ? 1 / (backgroundOption.colors.length - 1) : 1;
            for(let i=0; i < backgroundOption.colors.length; i++){
                gradient.addColorStop(i * step, backgroundOption.colors[i]);
            }
            ctx.fillStyle = gradient;
        } else if (backgroundOption.type === 'solid') {
            ctx.fillStyle = backgroundOption.color;
        }
        ctx.fillRect(0, 0, canvasWidth, canvasHeight);
        
        const fontChoice = (IMAGE_FONTS_DATA && IMAGE_FONTS_DATA[selectedFontKey]) || { family: '"Noto Naskh Arabic", Arial, Tahoma, sans-serif' };
        
        const verseFontFamily = fontChoice.family;
        const verseFontSize = '32px';
        const referenceFontFamily = fontChoice.family;
        const referenceFontSize = '24px';
        const watermarkFontFamily = 'Arial, sans-serif';
        const watermarkFontSize = '18px';
        const siteLinkFontFamily = 'Arial, sans-serif';
        const siteLinkFontSize = '16px';

        ctx.fillStyle = textColor; 
        ctx.direction = 'rtl';
        const padding = 40; 
        const maxWidth = canvasWidth - (padding * 2);
        let yPosition = padding + 50;

        function wrapText(context, text, x, y, maxLineWidth, lineHeight, fontStyle, fontFamily, fontSize) {
            context.font = fontStyle + " " + fontSize + " " + fontFamily;
            context.textAlign = 'right';
            const words = text.split(/\s+/);
            let line = '';
            const lines = [];
            for(let n = 0; n < words.length; n++) {
                const testLine = line + words[n] + ' ';
                const metrics = context.measureText(testLine);
                const testWidth = metrics.width;
                if (testWidth > maxLineWidth && n > 0) {
                    lines.push(line.trim());
                    line = words[n] + ' ';
                } else {
                    line = testLine;
                }
            }
            lines.push(line.trim());
            lines.forEach(singleLine => {
                context.fillText(singleLine, x, y);
                y += lineHeight;
            });
            return y;
        }

        yPosition = wrapText(ctx, verseText, canvasWidth - padding, yPosition, maxWidth, 45, 'bold', verseFontFamily, verseFontSize);
        yPosition += 10;

        ctx.font = "italic " + referenceFontSize + " " + referenceFontFamily;
        ctx.textAlign = 'left';
        const referenceTextHeight = parseFloat(referenceFontSize); 
        if (yPosition + referenceTextHeight > canvasHeight - padding - 60) {
             yPosition = canvasHeight - padding - 60 - referenceTextHeight;
        }
        ctx.fillText(verseReference, padding, yPosition);
        yPosition += referenceTextHeight + 20;

        const bottomPadding = canvasHeight - padding;
        ctx.font = siteLinkFontSize + " " + siteLinkFontFamily;
        ctx.fillStyle = textColor === '#FFFFFF' ? 'rgba(255, 255, 255, 0.55)' : 'rgba(0, 0, 0, 0.40)';
        ctx.textAlign = 'right';
        const siteLinkText = "WwW.OrSoZoX.CoM";
        ctx.fillText(siteLinkText, canvasWidth - padding, bottomPadding);

        ctx.font = watermarkFontSize + " " + watermarkFontFamily;
        ctx.fillStyle = textColor === '#FFFFFF' ? 'rgba(255, 255, 255, 0.65)' : 'rgba(0, 0, 0, 0.45)';
        ctx.textAlign = 'right';
        const watermarkText = (IMAGE_GENERATOR_STRINGS && IMAGE_GENERATOR_STRINGS.website_credit) ? IMAGE_GENERATOR_STRINGS.website_credit : get_bloginfo_name_js_equivalent();
        ctx.fillText(watermarkText, canvasWidth - padding, bottomPadding - parseFloat(siteLinkFontSize) - 5);

        if ($imageContainer.length) {
            $imageContainer.html('');
            const imgElement = $('<img>', { src: canvas.toDataURL('image/png'), alt: verseReference, css: { 'max-width': '100%', 'border-radius': '8px', 'margin-top': '15px', 'border': '1px solid ' + (textColor === '#FFFFFF' ? '#555' : '#ccc') } });
            const downloadText = (IMAGE_GENERATOR_STRINGS && IMAGE_GENERATOR_STRINGS.download_image) ? IMAGE_GENERATOR_STRINGS.download_image : 'تحميل الصورة';
            const downloadLink = $('<a>', { href: imgElement.attr('src'), download: `OrSoZoX-${verseReference.replace(/[:\s+\W]/g, '_').replace(/[^a-zA-Z0-9أ-ي_-]/g, '')}.png`, text: downloadText, class: 'bible-control-button download-image-button', css: { 'display': 'block', 'text-align': 'center', 'margin-top': '10px' } });
            $imageContainer.append(imgElement).append(downloadLink);
        }
    }
    function get_bloginfo_name_js_equivalent() {
        if (typeof bibleFrontend !== 'undefined' && bibleFrontend.site_name) {
            return bibleFrontend.site_name;
        }
        const titleParts = document.title.split(' - ');
        return titleParts.length > 1 ? titleParts[titleParts.length - 1] : document.title;
    }

    function showLoadingInVersesDisplay(message = 'جارٍ التحميل...') {
        if ($versesDisplay && $versesDisplay.length) {
            $versesDisplay.html(`<p class="bible-loading-message"><i class="fas fa-spinner fa-spin"></i> ${message}</p>`);
        }
    }

    const siteName = $('meta[property="og:site_name"]').attr('content') || (typeof bibleFrontend !== 'undefined' && bibleFrontend.site_name) || document.title.split(' - ').pop().trim() || '';
    if (!$('body').data('original-browser-title')) { $('body').data('original-browser-title', document.title); }
    if (!$('body').data('original-meta-description')) { $('body').data('original-meta-description', $('meta[name="description"]').attr('content') || ''); }
    if ($mainPageTitleElement && $mainPageTitleElement.length && !$('body').data('original-page-title')) { $('body').data('original-page-title', $mainPageTitleElement.text().trim()); }

    function resetVersesDisplay(message = 'يرجى اختيار السفر ثم الأصحاح لعرض الآيات.') {
        if ($versesDisplay && $versesDisplay.length) {
            $versesDisplay.html(`<p class="bible-select-prompt">${message}</p>`);
        }
        const originalPageTitle = $('body').data('original-page-title') || 'الكتاب المقدس';
        const originalBrowserTitle = $('body').data('original-browser-title') || (originalPageTitle + (siteName ? ' - ' + siteName : ''));
        const originalMetaDesc = $('body').data('original-meta-description') || '';

        if ($mainPageTitleElement && $mainPageTitleElement.length) {
            $mainPageTitleElement.text(originalPageTitle);
        } else if ($versesDisplay && $versesDisplay.length) {
            $versesDisplay.find('h1#bible-ajax-title').remove();
        }
        document.title = originalBrowserTitle;
        $('meta[name="description"]').attr('content', originalMetaDesc);
        try {
            const normalizedBaseUrl = BASE_URL.endsWith('/') ? BASE_URL : BASE_URL + '/';
            if (window.location.href.replace(/\?.*$/,'').replace(/\/$/,'') !== normalizedBaseUrl.replace(/\/$/,'')) {
                 history.pushState({ book: null, chapter: null, testament: DEFAULT_TESTAMENT_VIEW, path: normalizedBaseUrl }, originalBrowserTitle, normalizedBaseUrl);
            }
        } catch (e) { console.error("Error in history.pushState during reset: ", e); }
    }

    function updatePageDetails(pageTitleText, metaDescription, bookNameForUrl, chapterNumForUrl, testamentValForState) {
        let browserFullTitle = pageTitleText;
        if (siteName && pageTitleText !== siteName && !pageTitleText.includes(siteName)) {
            browserFullTitle += ' - ' + siteName;
        }
        document.title = browserFullTitle;
        $('meta[name="description"]').attr('content', metaDescription);

        let $titleElementToUpdate = $mainPageTitleElement;
        if (!$titleElementToUpdate || !$titleElementToUpdate.length) {
             $titleElementToUpdate = $('h1#bible-main-page-title').first();
             if (!$titleElementToUpdate.length && $versesDisplay && $versesDisplay.length) {
                 $titleElementToUpdate = $versesDisplay.find('h1#bible-ajax-title').first();
                 if (!$titleElementToUpdate.length) {
                     const $controls = $versesDisplay.find('.bible-controls-wrapper');
                     if ($controls.length) { $controls.before('<h1 id="bible-ajax-title"></h1>'); }
                     else { $versesDisplay.prepend('<h1 id="bible-ajax-title"></h1>'); }
                     $titleElementToUpdate = $versesDisplay.find('h1#bible-ajax-title');
                 }
             }
        }
        if ($titleElementToUpdate && $titleElementToUpdate.length) {
            $titleElementToUpdate.text(pageTitleText);
        }

        let relativePath = "";
        try {
            const baseUrlObject = new URL(BASE_URL);
            relativePath = baseUrlObject.pathname; 
        } catch (e) {
            console.error("Error parsing BASE_URL in updatePageDetails. BASE_URL:", BASE_URL, e);
            relativePath = BASE_URL.startsWith('/') ? BASE_URL : '/' + BASE_URL;
        }
        if (!relativePath.endsWith('/')) { relativePath += '/'; }

        const createSlug = (str) => {
            if (!str) return ''; let slug = String(str).trim();
            slug = removeArabicTashkeel(slug); slug = slug.replace(/\s+/g, '-');
            slug = slug.replace(/[^a-zA-Z0-9\u0600-\u06FF\-]/g, '');
            return encodeURIComponent(slug);
        };

        if (bookNameForUrl) {
            const bookSlug = createSlug(bookNameForUrl);
            relativePath += bookSlug + '/';
            if (chapterNumForUrl) {
                relativePath += String(chapterNumForUrl) + '/';
            }
        }
        
        let finalPushUrl;
        try {
            const tempUrl = new URL(relativePath, window.location.origin);
            finalPushUrl = tempUrl.href;
        } catch(e) {
             console.error("CRITICAL: Failed to construct final URL for pushState. Path:", relativePath, "Origin:", window.location.origin, e);
             finalPushUrl = BASE_URL + (bookNameForUrl ? createSlug(bookNameForUrl) + '/' + (chapterNumForUrl ? chapterNumForUrl + '/' : '') : '');
             finalPushUrl = finalPushUrl.replace(/([^:])\/\//g, '$1/');
        }
        
        try {
            const currentNormalized = (window.location.origin + window.location.pathname + window.location.search).replace(/\/?(\?.*)?$/, '');
            const finalNormalized = finalPushUrl.replace(/\/?(\?.*)?$/, '');

            if (currentNormalized !== finalNormalized) {
                 history.pushState({ 
                     book: bookNameForUrl, 
                     chapter: chapterNumForUrl, 
                     testament: testamentValForState,
                     path: finalPushUrl 
                    }, browserFullTitle, finalPushUrl);
            }
        } catch (e) { 
            console.error("Error in history.pushState: ", e, "Attempted URL:", finalPushUrl, "Current Location:", window.location.href); 
        }
    }

    function updateBookDropdown(selectedTestamentValue, preselectedBookName = null, preselectedChapter = null, isInitialLoad = false) {
        if (!$bookSelect || !$bookSelect.length) return;
        $bookSelect.prop('disabled', true).empty().append(`<option value="">جارٍ التحميل...</option>`);
        if($chapterSelect && $chapterSelect.length) {
            $chapterSelect.prop('disabled', true).empty().append(`<option value="">اختر الأصحاح</option>`);
        }
        if (!isInitialLoad && $versesDisplay && $versesDisplay.length && ($versesDisplay.find('.verse-text').length > 0 || $versesDisplay.find('.bible-select-prompt').length === 0)) {
             resetVersesDisplay('يرجى اختيار السفر ثم الأصحاح لعرض الآيات.');
        }
        $.ajax({
            url: AJAX_URL, type: 'POST',
            data: { action: 'bible_get_books_by_testament', testament: selectedTestamentValue, nonce: AJAX_NONCE },
            dataType: 'json',
            success: function(response) {
                $bookSelect.empty().append(`<option value="">اختر السفر</option>`);
                if (response.success && response.data && Array.isArray(response.data) && response.data.length > 0) {
                    $.each(response.data, function(index, bookName) {
                        $bookSelect.append($('<option>', { value: bookName, text: bookName }));
                    });
                    $bookSelect.prop('disabled', false);
                    if (preselectedBookName && $bookSelect.find('option[value="' + preselectedBookName.replace(/"/g, '\\"') + '"]').length > 0) {
                        $bookSelect.val(preselectedBookName);
                        loadChaptersForBook($bookSelect.val(), preselectedChapter, isInitialLoad);
                    } else if (isInitialLoad && $versesDisplay && $versesDisplay.length && $versesDisplay.find('.verse-text').length === 0) {
                        resetVersesDisplay();
                    }
                } else {
                    $bookSelect.append(`<option value="" disabled>لا توجد أسفار لهذا العهد</option>`);
                    if ($versesDisplay && $versesDisplay.length && (!isInitialLoad || $versesDisplay.find('.verse-text').length === 0)) {
                         resetVersesDisplay('لا توجد أسفار لهذا العهد');
                    }
                }
            },
            error: function(jqXHR) { 
                console.error("AJAX Error (get_books_by_testament):", jqXHR.status, jqXHR.responseText);
                $bookSelect.empty().append(`<option value="" disabled>خطأ في تحميل الأسفار</option>`);
                if ($versesDisplay && $versesDisplay.length && (!isInitialLoad || $versesDisplay.find('.verse-text').length === 0)) {
                    resetVersesDisplay('خطأ في تحميل الأسفار');
                }
            }
        });
    }

    function loadChaptersForBook(selectedBook, preselectedChapter = null, isInitialLoad = false) {
        if (!$chapterSelect || !$chapterSelect.length) return;
        $chapterSelect.empty().prop('disabled', true).append(`<option value="">جارٍ التحميل...</option>`);
        
        if (!isInitialLoad && $versesDisplay && $versesDisplay.length) {
            if (preselectedChapter !== "1" || !preselectedChapter) {
                 resetVersesDisplay('يرجى اختيار الأصحاح.');
            }
        }

        if (!selectedBook) {
            if ($versesDisplay && $versesDisplay.length && (!isInitialLoad || $versesDisplay.find('.verse-text').length === 0)) {
                resetVersesDisplay();
            }
            $chapterSelect.empty().append(`<option value="">اختر الأصحاح</option>`).prop('disabled', true);
            return;
        }
        $.ajax({
            url: AJAX_URL, type: 'POST',
            data: { action: 'bible_get_chapters', book: selectedBook, nonce: AJAX_NONCE },
            dataType: 'json',
            success: function(response) {
                $chapterSelect.empty().append(`<option value="">اختر الأصحاح</option>`);
                if (response.success && response.data && Array.isArray(response.data) && response.data.length > 0) {
                    $.each(response.data, function(index, chapter) {
                        $chapterSelect.append($('<option>', { value: chapter, text: chapter }));
                    });
                    $chapterSelect.prop('disabled', false);
                    
                    let chapterToActuallySelect = null;
                    if (!isInitialLoad && preselectedChapter === "1" && $chapterSelect.find('option[value="1"]').length > 0) {
                        chapterToActuallySelect = "1";
                    } 
                    else if (isInitialLoad && preselectedChapter && $chapterSelect.find('option[value="' + preselectedChapter + '"]').length > 0) {
                        chapterToActuallySelect = preselectedChapter;
                    } 
                    else if (isInitialLoad && $chapterSelect.data('initial-chapter') && $chapterSelect.find('option[value="' + $chapterSelect.data('initial-chapter') + '"]').length > 0) {
                        chapterToActuallySelect = $chapterSelect.data('initial-chapter');
                    }

                    if (chapterToActuallySelect) {
                        $chapterSelect.val(chapterToActuallySelect);
                        loadVersesForChapter(selectedBook, chapterToActuallySelect);
                    } else if ($versesDisplay && $versesDisplay.length && $versesDisplay.find('.verse-text').length === 0 && $versesDisplay.find('.bible-loading-message').length === 0) {
                         if (!isInitialLoad) { 
                            resetVersesDisplay('يرجى اختيار الأصحاح.');
                         }
                    }
                } else { 
                    const errorMsg = 'لم يتم العثور على أصحاحات.';
                    $chapterSelect.append(`<option value="" disabled>${errorMsg}</option>`);
                    if ($versesDisplay && $versesDisplay.length && (!isInitialLoad || $versesDisplay.find('.verse-text').length === 0)) {
                         resetVersesDisplay(errorMsg);
                    }
                 }
            },
            error: function(jqXHR) { 
                const errorMsg = 'خطأ في الاتصال (أصحاحات).';
                $chapterSelect.empty().append(`<option value="" disabled>${errorMsg}</option>`);
                if ($versesDisplay && $versesDisplay.length && (!isInitialLoad || $versesDisplay.find('.verse-text').length === 0)) {
                    resetVersesDisplay(errorMsg);
                }
            }
        });
    }

    function loadVersesForChapter(selectedBook, selectedChapter) {
        if (!$versesDisplay || !$versesDisplay.length) return;
        const selectedTestament = ($testamentSelect && $testamentSelect.length) ? $testamentSelect.val() : 'all';
        if (!selectedBook || !selectedChapter) {
            resetVersesDisplay();
            return;
        }
        showLoadingInVersesDisplay();
        $.ajax({
            url: AJAX_URL, type: 'POST',
            data: { action: 'bible_get_verses', book: selectedBook, chapter: selectedChapter, nonce: AJAX_NONCE },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.html) {
                    $versesDisplay.html(response.data.html);
                    initializeSelectors(); 
                    updatePageDetails( response.data.title, response.data.description, response.data.book, response.data.chapter, selectedTestament );
                } else { 
                    const errorMsg = (response.data && response.data.message) ? response.data.message : 'حدث خطأ أثناء تحميل الآيات.';
                    $versesDisplay.html(`<p class="bible-error-message">${errorMsg}</p>`);
                 }
            },
            error: function(jqXHR) { 
                $versesDisplay.html(`<p class="bible-error-message">خطأ في الاتصال (آيات).</p>`);
            }
        });
    }

    // --- معالجات الأحداث ---
    
    function setupModal($modal) {
        if (!$modal || !$modal.length) return;
        function closeModal() { $modal.removeClass('active'); }
        $modal.find('.bible-modal-close-button').first().on('click', closeModal);
        $modal.on('click', function(e) { if (e.target === this) { closeModal(); } });
        return closeModal;
    }

    const closeDefinitionModal = setupModal($definitionModal);
    const closeChapterTermsModal = setupModal($chapterTermsModal);

    $('body').on('click', '.bible-term', function(e) {
        e.preventDefault();
        const term = $(this).text();
        const definition = $(this).data('definition');
        if (term && definition) {
            $modalTerm.text(term);
            $modalDefinition.html(definition);
            $definitionModal.addClass('active');
        }
    });
    
    $(document).on('keyup', function(e) {
        if (e.key === "Escape") {
            if ($definitionModal.hasClass('active')) closeDefinitionModal();
            if ($chapterTermsModal.hasClass('active')) closeChapterTermsModal();
        }
    });

    if ($bibleContentContainer.length) {
        $testamentSelect.on('change', function() {
            updateBookDropdown($(this).val());
        });
        $bookSelect.on('change', function() {
            loadChaptersForBook($(this).val(), "1"); 
        });
        $chapterSelect.on('change', function() {
            loadVersesForChapter($bookSelect.val(), $(this).val());
        });
        $(document.body).on('click', '#bible-verses-display .chapter-navigation a.ajax-nav-link', function(event) {
            event.preventDefault();
            const $link = $(this);
            const bookName = $link.data('book');
            const chapterNum = $link.data('chapter');
            if (bookName && chapterNum) {
                if ($bookSelect.val() !== bookName) {
                    $bookSelect.val(bookName);
                    loadChaptersForBook(bookName, chapterNum);
                } else {
                    $chapterSelect.val(String(chapterNum));
                    loadVersesForChapter(bookName, chapterNum);
                }
            }
        });
    }

    $(document.body).on('click', '.bible-control-button', function(event) {
        const $button = $(this);
        let $contentArea = $button.closest('.bible-content-area, .bible-search-results, .random-verse-widget, .daily-verse-widget, #bible-container, .bible-single-verse-container');
        if (!$contentArea.length) $contentArea = $('body');
        const action = $button.data('action');

        if (!$(this).hasClass('ajax-nav-link') && !$(this).hasClass('download-image-button')) {
             event.preventDefault();
        }

        let $textContainer = $contentArea.find('#verses-content, .verses-text-container, .verse-text-container').first();
        if (!$textContainer.length) $textContainer = $contentArea;

        switch (action) {
            case 'toggle-tashkeel':
                const isTashkeelRemoved = $button.data('tashkeel-removed');
                if (!isTashkeelRemoved) {
                    setTashkeelState($textContainer, false);
                    $button.find('.label').text(BIBLE_STRINGS.show_tashkeel_label || 'إظهار التشكيل');
                    $button.data('tashkeel-removed', true);
                } else {
                    setTashkeelState($textContainer, true);
                    $button.find('.label').text(BIBLE_STRINGS.hide_tashkeel_label || 'إلغاء التشكيل');
                    $button.data('tashkeel-removed', false);
                }
                break;
            
            case 'show-chapter-terms':
                const terms = new Map();
                $contentArea.find('a.bible-term').each(function() {
                    const termText = $(this).text();
                    const termDef = $(this).data('definition');
                    if (!terms.has(termText)) { terms.set(termText, termDef); }
                });

                if (terms.size > 0) {
                    let listHtml = '<dl>';
                    const sortedTerms = new Map([...terms.entries()].sort());
                    sortedTerms.forEach((def, term) => {
                        listHtml += `<dt>${term}</dt><dd>${def}</dd>`;
                    });
                    listHtml += '</dl>';
                    $chapterTermsList.html(listHtml);
                } else {
                    $chapterTermsList.html(`<p>${BIBLE_STRINGS.no_terms_found || 'لم يتم العثور على كلمات.'}</p>`);
                }
                $chapterTermsModal.addClass('active');
                break;
            
            case 'dark-mode-toggle':
                toggleDarkMode();
                break;
            
            case 'increase-font':
            case 'decrease-font':
                const change = (action === 'increase-font') ? 1 : -1;
                const minSize = 10, maxSize = 48;
                $textContainer.find('.verse-text .text-content, .verse-text .verse-number, .verse-text .verse-reference-link, .search-result-item .search-result-text, .search-result-item .search-result-reference').each(function() {
                    const $el = $(this);
                    let currentSize = parseFloat($el.css('font-size'));
                    if (isNaN(currentSize)) currentSize = parseFloat(window.getComputedStyle(this).fontSize) || 16;
                    let newSize = currentSize + change;
                    newSize = Math.max(minSize, Math.min(maxSize, newSize));
                    $el.css('font-size', newSize + 'px');
                });
                break;

            case 'read-aloud':
                handleReadAloud($contentArea, $button);
                break;
            
            case 'generate-image':
                const verseText = $button.data('verse-text');
                const verseReference = $button.data('verse-reference');
                const $controlsWrapper = $button.closest('.bible-image-generator-controls, .bible-controls-wrapper');
                const $fontSelect = $controlsWrapper.find('select[id^="bible-image-font-select"]');
                const $bgSelect = $controlsWrapper.find('select[id^="bible-image-bg-select"]');
                const selectedFont = $fontSelect.length ? $fontSelect.val() : 'noto_naskh_arabic';
                const selectedBackground = $bgSelect.length ? $bgSelect.val() : 'gradient_purple_blue';

                if (verseText && verseReference) {
                    const $imageContainer = $contentArea.find('#verse-image-container');
                    if ($imageContainer.length) {
                        const generatingText = (IMAGE_GENERATOR_STRINGS && IMAGE_GENERATOR_STRINGS.generating_image) ? IMAGE_GENERATOR_STRINGS.generating_image : 'جارٍ إنشاء الصورة...';
                        $imageContainer.html(`<p class="bible-loading-message">${generatingText}</p>`);
                        setTimeout(() => generateVerseImage(verseText, verseReference, $imageContainer, selectedFont, selectedBackground), 50);
                    }
                }
                break;
        }
    });

    window.addEventListener('popstate', function(event) {
        if (!$bibleContentContainer.length) return;
        initializeSelectors(); 

        const state = event.state;
        if (state && state.path) {
            const testamentFromState = state.testament || DEFAULT_TESTAMENT_VIEW;
            if ($testamentSelect.val() !== testamentFromState) {
                $testamentSelect.val(testamentFromState);
            }
            updateBookDropdown(testamentFromState, state.book, state.chapter);
        } else if (window.location.href.replace(/\/$/, '').replace(/\?.*$/,'') === BASE_URL.replace(/\/$/, '')) {
            resetVersesDisplay();
            $testamentSelect.val(DEFAULT_TESTAMENT_VIEW);
            $bookSelect.val('');
            $chapterSelect.empty().append(`<option value="">اختر الأصحاح</option>`).prop('disabled', true);
            updateBookDropdown(DEFAULT_TESTAMENT_VIEW);
        }
    });
    
    // --- Initial page load logic ---
    applyDarkModePreference();
    if ($bibleContentContainer.length || $('.random-verse-widget').length || $('.daily-verse-widget').length || $('.bible-single-verse-container').length) {
        initializeSelectors(); 

        if ($bibleContentContainer.length) { 
            const pathSegments = window.location.pathname.replace(/\/$/, "").split('/').filter(segment => segment.length > 0);
            const baseSegments = BASE_URL.replace(/\/$/, "").split('/').filter(segment => segment.length > 0);
            let isBiblePage = pathSegments.length >= baseSegments.length && baseSegments.every((seg, i) => seg === pathSegments[i]);
            
            let initialBookNameFromData = $bookSelect.data('initial-book');
            let initialChapterNumFromData = $chapterSelect.data('initial-chapter');
            let initialTestament = $testamentSelect.val() || DEFAULT_TESTAMENT_VIEW;

            const isContentAlreadyLoadedByPHP = $versesDisplay.find('.verse-text').length > 0;

            if (isContentAlreadyLoadedByPHP && initialBookNameFromData && initialChapterNumFromData) {
                const testamentOfLoadedBook = $bookSelect.data('current-testament');
                if (testamentOfLoadedBook) {
                    initialTestament = testamentOfLoadedBook;
                    $testamentSelect.val(initialTestament);
                }
                updateBookDropdown(initialTestament, initialBookNameFromData, initialChapterNumFromData, true);
                const currentH1Text = $mainPageTitleElement.text() || (initialBookNameFromData + ' ' + initialChapterNumFromData);
                const currentMetaDesc = $('meta[name="description"]').attr('content');
                updatePageDetails(currentH1Text, currentMetaDesc, initialBookNameFromData, initialChapterNumFromData, initialTestament);
            } else if (isBiblePage && pathSegments.length > baseSegments.length) {
                const bookSlugFromUrl = decodeURIComponent(pathSegments[baseSegments.length]);
                const chapterFromUrl = (pathSegments.length > baseSegments.length + 1) ? pathSegments[baseSegments.length + 1] : null;
                updateBookDropdown(initialTestament, bookSlugFromUrl, chapterFromUrl, true);
            }
            else {
                updateBookDropdown(initialTestament, initialBookNameFromData, initialChapterNumFromData, true);
            }
        }
    }
});
