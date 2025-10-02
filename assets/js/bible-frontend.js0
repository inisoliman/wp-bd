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
    
    const IMAGE_FONTS_DATA = (typeof bibleFrontend !== 'undefined' && bibleFrontend.image_fonts_data) ? bibleFrontend.image_fonts_data : {
        'noto_naskh_arabic': { label: BIBLE_STRINGS.font_noto_naskh || 'خط نسخ (افتراضي)', family: '"Noto Naskh Arabic", Arial, Tahoma, sans-serif' },
        'amiri': { label: BIBLE_STRINGS.font_amiri || 'خط أميري', family: 'Amiri, Georgia, serif' },
        'tahoma': { label: BIBLE_STRINGS.font_tahoma || 'خط تاهوما', family: 'Tahoma, Geneva, sans-serif' },
        'arial': { label: BIBLE_STRINGS.font_arial || 'خط آريال', family: 'Arial, Helvetica, sans-serif' },
        'times_new_roman': { label: BIBLE_STRINGS.font_times || 'خط تايمز نيو رومان', family: '"Times New Roman", Times, serif' }
    };
    const IMAGE_BACKGROUNDS_DATA = (typeof bibleFrontend !== 'undefined' && bibleFrontend.image_backgrounds_data) ? bibleFrontend.image_backgrounds_data : {
        'gradient_purple_blue': { type: 'gradient', colors: ['#4B0082', '#00008B', '#2F4F4F'], label: BIBLE_STRINGS.bg_gradient_purple_blue || 'تدرج بنفسجي-أزرق', textColor: '#FFFFFF'},
        'gradient_blue_green': { type: 'gradient', colors: ['#007bff', '#28a745', '#17a2b8'], label: BIBLE_STRINGS.bg_gradient_blue_green || 'تدرج أزرق-أخضر', textColor: '#FFFFFF' },
        'solid_dark_grey': { type: 'solid', color: '#343a40', label: BIBLE_STRINGS.bg_solid_dark_grey || 'رمادي داكن ثابت', textColor: '#FFFFFF' },
        'solid_light_beige': { type: 'solid', color: '#f5f5dc', label: BIBLE_STRINGS.bg_solid_light_beige || 'بيج فاتح ثابت', textColor: '#222222' },
    };

    const $bibleContentContainer = $('#bible-container');
    let $testamentSelect, $bookSelect, $chapterSelect, $versesDisplay, $mainPageTitleElement;

    function initializeSelectors() {
        if ($bibleContentContainer.length) {
            $testamentSelect = $bibleContentContainer.find('#bible-testament-select');
            $bookSelect = $bibleContentContainer.find('#bible-book-select');
            $chapterSelect = $bibleContentContainer.find('#bible-chapter-select');
            $versesDisplay = $bibleContentContainer.find('#bible-verses-display');
        }
        $mainPageTitleElement = $('h1#bible-main-page-title').first();
        if (!$mainPageTitleElement.length && $versesDisplay && $versesDisplay.length) {
            $mainPageTitleElement = $versesDisplay.find('h1#bible-ajax-title').first();
        }
        populateImageOptionSelects();
    }
    
    function populateImageOptionSelects() {
        $('select[id^="bible-image-font-select"]').each(function() {
            const $select = $(this);
            if ($select.children('option').length <= 1 && ($select.children('option').length === 0 || $select.children('option').first().val() === "")) {
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
            if ($select.children('option').length <= 1 && ($select.children('option').length === 0 || $select.children('option').first().val() === "")) {
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
        text = text.replace(/[\u064B-\u0652\u0670]/g, '');
        text = text.replace(/\u0640/g, '');
        return text;
    }

    function applyDarkModePreference() {
        const isDarkMode = localStorage.getItem('darkMode') === 'enabled' || (localStorage.getItem('darkMode') === null && DEFAULT_DARK_MODE);
        const $body = $('body');
        const $toggleButton = $('.dark-mode-toggle-button');
        if (isDarkMode) {
            $body.addClass('dark-mode');
            $toggleButton.find('.label').text(bibleFrontend.dark_mode_toggle_label_light || 'الوضع النهاري');
            $toggleButton.find('i').removeClass('fa-moon').addClass('fa-sun');
        } else {
            $body.removeClass('dark-mode');
            $toggleButton.find('.label').text(bibleFrontend.dark_mode_toggle_label_dark || 'الوضع الليلي');
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
            alert(BIBLE_STRINGS.speech_unsupported || 'عذراً، متصفحك لا يدعم ميزة القراءة الصوتية.'); return;
        }
        if (isReading) {
            speechSynthesis.cancel(); isReading = false;
            $button.find('.label').text(bibleFrontend.read_aloud_label || 'قراءة بصوت عالٍ');
            $button.find('i').removeClass('fa-stop-circle').addClass('fa-volume-up'); return;
        }
        let textToRead = '';
        const $textElements = $contentArea.find('.verses-text-container .verse-text .text-content, .verse-text-container .verse-text .text-content');
        if ($textElements.length > 0) {
            $textElements.each(function() { textToRead += $(this).text().trim() + ' '; });
        } else {
            const $singleSourceText = $contentArea.find('.random-verse .text-content, .daily-verse .text-content, .search-result-item .text-content');
            if ($singleSourceText.length > 0) {
                 textToRead = $singleSourceText.map(function() { return $(this).text().trim(); }).get().join(' ');
            }
        }
        if (textToRead.trim() === '') { alert(BIBLE_STRINGS.no_text_to_read || 'لا يوجد نص للقراءة.'); return; }
        currentUtterance = new SpeechSynthesisUtterance(textToRead.trim());
        const arabicVoice = getArabicVoice();
        if (arabicVoice) { currentUtterance.voice = arabicVoice; } 
        else { console.warn("لم يتم العثور على صوت عربي."); currentUtterance.lang = 'ar-SA'; }
        currentUtterance.pitch = 1; currentUtterance.rate = 0.9;
        currentUtterance.onstart = () => {
            isReading = true;
            $button.find('.label').text(bibleFrontend.stop_reading_label || 'إيقاف القراءة');
            $button.find('i').removeClass('fa-volume-up').addClass('fa-stop-circle');
        };
        currentUtterance.onend = () => {
            isReading = false;
            $button.find('.label').text(bibleFrontend.read_aloud_label || 'قراءة بصوت عالٍ');
            $button.find('i').removeClass('fa-stop-circle').addClass('fa-volume-up');
            currentUtterance = null;
        };
        currentUtterance.onerror = (event) => {
            console.error('SpeechSynthesisUtterance.onerror', event);
            alert((BIBLE_STRINGS.error_reading_aloud || 'حدث خطأ أثناء محاولة القراءة: ') + event.error);
            isReading = false;
            $button.find('.label').text(bibleFrontend.read_aloud_label || 'قراءة بصوت عالٍ');
            $button.find('i').removeClass('fa-stop-circle').addClass('fa-volume-up');
        };
        speechSynthesis.speak(currentUtterance);
    }

    function generateVerseImage(verseText, verseReference, $imageContainer, selectedFontKey, selectedBgKey) {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (!ctx) { alert(IMAGE_GENERATOR_STRINGS.canvas_unsupported || 'Canvas not supported'); return; }

        const canvasWidth = 800, canvasHeight = 500;
        canvas.width = canvasWidth; canvas.height = canvasHeight;

        const backgroundOption = IMAGE_BACKGROUNDS_DATA[selectedBgKey] || IMAGE_BACKGROUNDS_DATA.gradient_purple_blue;
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
        
        const fontChoice = IMAGE_FONTS_DATA[selectedFontKey] || IMAGE_FONTS_DATA['noto_naskh_arabic'];
        
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
        const watermarkText = IMAGE_GENERATOR_STRINGS.website_credit || get_bloginfo_name_js_equivalent();
        ctx.fillText(watermarkText, canvasWidth - padding, bottomPadding - parseFloat(siteLinkFontSize) - 5);

        if ($imageContainer.length) {
            $imageContainer.html('');
            const imgElement = $('<img>', { src: canvas.toDataURL('image/png'), alt: verseReference, css: { 'max-width': '100%', 'border-radius': '8px', 'margin-top': '15px', 'border': '1px solid ' + (textColor === '#FFFFFF' ? '#555' : '#ccc') } });
            const downloadLink = $('<a>', { href: imgElement.attr('src'), download: `OrSoZoX-${verseReference.replace(/[:\s+\W]/g, '_').replace(/[^a-zA-Z0-9أ-ي_-]/g, '')}.png`, text: IMAGE_GENERATOR_STRINGS.download_image || 'تحميل الصورة', class: 'bible-control-button download-image-button', css: { 'display': 'block', 'text-align': 'center', 'margin-top': '10px' } });
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

    function showLoadingInVersesDisplay(message = BIBLE_STRINGS.loading || 'جارٍ التحميل...') {
        if ($versesDisplay && $versesDisplay.length) {
            $versesDisplay.html(`<p class="bible-loading-message"><i class="fas fa-spinner fa-spin"></i> ${message}</p>`);
        }
    }

    const siteName = $('meta[property="og:site_name"]').attr('content') || (typeof bibleFrontend !== 'undefined' && bibleFrontend.site_name) || document.title.split(' - ').pop().trim() || '';
    if (!$('body').data('original-browser-title')) { $('body').data('original-browser-title', document.title); }
    if (!$('body').data('original-meta-description')) { $('body').data('original-meta-description', $('meta[name="description"]').attr('content') || (BIBLE_STRINGS.mainPageDescription || '')); }
    if ($mainPageTitleElement && $mainPageTitleElement.length && !$('body').data('original-page-title')) { $('body').data('original-page-title', $mainPageTitleElement.text().trim()); }

    function resetVersesDisplay(message = BIBLE_STRINGS.please_select_book_and_chapter || 'يرجى اختيار السفر ثم الأصحاح لعرض الآيات.') {
        if ($versesDisplay && $versesDisplay.length) {
            $versesDisplay.html(`<p class="bible-select-prompt">${message}</p>`);
        }
        const originalPageTitle = $('body').data('original-page-title') || BIBLE_STRINGS.mainPageTitle || 'الكتاب المقدس';
        const originalBrowserTitle = $('body').data('original-browser-title') || (originalPageTitle + (siteName ? ' - ' + siteName : ''));
        const originalMetaDesc = $('body').data('original-meta-description') || BIBLE_STRINGS.mainPageDescription || '';

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
        $bookSelect.prop('disabled', true).empty().append(`<option value="">${BIBLE_STRINGS.loading || 'جارٍ التحميل...'}</option>`);
        if($chapterSelect && $chapterSelect.length) {
            $chapterSelect.prop('disabled', true).empty().append(`<option value="">${BIBLE_STRINGS.select_chapter || 'اختر الأصحاح'}</option>`);
        }
        if (!isInitialLoad && $versesDisplay && $versesDisplay.length && ($versesDisplay.find('.verse-text').length > 0 || $versesDisplay.find('.bible-select-prompt').length === 0)) {
             resetVersesDisplay(BIBLE_STRINGS.please_select_book_and_chapter || 'يرجى اختيار السفر ثم الأصحاح لعرض الآيات.');
        }
        $.ajax({
            url: AJAX_URL, type: 'POST',
            data: { action: 'bible_get_books_by_testament', testament: selectedTestamentValue, nonce: AJAX_NONCE },
            dataType: 'json',
            success: function(response) {
                $bookSelect.empty().append(`<option value="">${BIBLE_STRINGS.select_book || 'اختر السفر'}</option>`);
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
                    $bookSelect.append(`<option value="" disabled>${BIBLE_STRINGS.no_books_found || 'لا توجد أسفار لهذا العهد'}</option>`);
                    if ($versesDisplay && $versesDisplay.length && (!isInitialLoad || $versesDisplay.find('.verse-text').length === 0)) {
                         resetVersesDisplay(BIBLE_STRINGS.no_books_found || 'لا توجد أسفار لهذا العهد');
                    }
                }
            },
            error: function(jqXHR) { 
                console.error("AJAX Error (get_books_by_testament):", jqXHR.status, jqXHR.responseText);
                $bookSelect.empty().append(`<option value="" disabled>${BIBLE_STRINGS.error_loading_books || 'خطأ في تحميل الأسفار'}</option>`);
                if ($versesDisplay && $versesDisplay.length && (!isInitialLoad || $versesDisplay.find('.verse-text').length === 0)) {
                    resetVersesDisplay(BIBLE_STRINGS.error_loading_books || 'خطأ في تحميل الأسفار');
                }
            }
        });
    }

    function loadChaptersForBook(selectedBook, preselectedChapter = null, isInitialLoad = false) {
        if (!$chapterSelect || !$chapterSelect.length) return;
        $chapterSelect.empty().prop('disabled', true).append(`<option value="">${BIBLE_STRINGS.loading || 'جارٍ التحميل...'}</option>`);
        
        if (!isInitialLoad && $versesDisplay && $versesDisplay.length) {
            // لا تقم بمسح الآيات هنا إذا كان preselectedChapter هو "1" (يعني أننا نريد تحميل الأصحاح الأول)
            // إلا إذا كان preselectedChapter فارغًا تمامًا
            if (preselectedChapter !== "1" || !preselectedChapter) {
                 resetVersesDisplay(BIBLE_STRINGS.please_select_chapter || 'يرجى اختيار الأصحاح.');
            }
        }

        if (!selectedBook) {
            if ($versesDisplay && $versesDisplay.length && (!isInitialLoad || $versesDisplay.find('.verse-text').length === 0)) {
                resetVersesDisplay();
            }
            $chapterSelect.empty().append(`<option value="">${BIBLE_STRINGS.select_chapter || 'اختر الأصحاح'}</option>`).prop('disabled', true);
            return;
        }
        $.ajax({
            url: AJAX_URL, type: 'POST',
            data: { action: 'bible_get_chapters', book: selectedBook, nonce: AJAX_NONCE },
            dataType: 'json',
            success: function(response) {
                $chapterSelect.empty().append(`<option value="">${BIBLE_STRINGS.select_chapter || 'اختر الأصحاح'}</option>`);
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
                         if (!isInitialLoad) { // فقط إذا لم يكن تحميلًا أوليًا، اطلب اختيار الأصحاح
                            resetVersesDisplay(BIBLE_STRINGS.please_select_chapter || 'يرجى اختيار الأصحاح.');
                         }
                    }
                } else { 
                    const errorMsg = (response.data && Array.isArray(response.data) && response.data.length === 0) ? (BIBLE_STRINGS.no_chapters_found || 'لم يتم العثور على أصحاحات لهذا السفر.') : (BIBLE_STRINGS.error_loading_chapters || 'حدث خطأ أثناء تحميل الأصحاحات.');
                    $chapterSelect.append(`<option value="" disabled>${errorMsg}</option>`);
                    if ($versesDisplay && $versesDisplay.length && (!isInitialLoad || $versesDisplay.find('.verse-text').length === 0)) {
                         resetVersesDisplay(errorMsg);
                    }
                 }
            },
            error: function(jqXHR) { 
                const errorMsg = BIBLE_STRINGS.error_loading_chapters_ajax || 'خطأ في الاتصال (أصحاحات). حاول مرة أخرى.';
                $chapterSelect.empty().append(`<option value="" disabled>${errorMsg}</option>`);
                if ($versesDisplay && $versesDisplay.length && (!isInitialLoad || $versesDisplay.find('.verse-text').length === 0)) {
                    resetVersesDisplay(errorMsg);
                }
            }
        });
    }

    function loadVersesForChapter(selectedBook, selectedChapter) {
        if (!$versesDisplay || !$versesDisplay.length) return;
        const selectedTestament = ($testamentSelect && $testamentSelect.length) ? $testamentSelect.val() : DEFAULT_TESTAMENT_VIEW;
        if (!selectedBook || !selectedChapter) {
            resetVersesDisplay(); return;
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
                    populateImageOptionSelects();
                    updatePageDetails( response.data.title, response.data.description, response.data.book, response.data.chapter, selectedTestament );
                } else { 
                    const errorMsg = (response.data && response.data.message) ? response.data.message : (BIBLE_STRINGS.error_loading_verses || 'حدث خطأ أثناء تحميل الآيات.');
                    $versesDisplay.html(`<p class="bible-error-message">${errorMsg}</p>`);
                 }
            },
            error: function(jqXHR) { 
                $versesDisplay.html(`<p class="bible-error-message">${BIBLE_STRINGS.error_loading_verses_ajax || 'خطأ في الاتصال (آيات). حاول مرة أخرى.'}</p>`);
            }
        });
    }

    // --- معالجات الأحداث ---
    if ($bibleContentContainer.length) {
        if ($testamentSelect && $testamentSelect.length) {
            $testamentSelect.on('change', function() {
                const selectedTestament = $(this).val();
                updateBookDropdown(selectedTestament, null, null, false);
            });
        }
        if ($bookSelect && $bookSelect.length) {
            $bookSelect.on('change', function() {
                const selectedBook = $(this).val();
                loadChaptersForBook(selectedBook, "1", false); 
            });
        }
        if ($chapterSelect && $chapterSelect.length) {
            $chapterSelect.on('change', function() {
                const selectedBook = ($bookSelect && $bookSelect.length) ? $bookSelect.val() : null;
                const selectedChapter = $(this).val();
                loadVersesForChapter(selectedBook, selectedChapter);
            });
        }
        $(document.body).on('click', '#bible-verses-display .chapter-navigation a.ajax-nav-link', function(event) {
            event.preventDefault();
            const $link = $(this);
            const bookName = $link.data('book');
            const chapterNum = $link.data('chapter');
            if (bookName && chapterNum) {
                if ($bookSelect && $bookSelect.length && $bookSelect.val() !== bookName) {
                    $bookSelect.val(bookName);
                    loadChaptersForBook(bookName, chapterNum, false);
                } else if ($chapterSelect && $chapterSelect.length && $chapterSelect.val() !== String(chapterNum)) {
                    $chapterSelect.val(String(chapterNum));
                    loadVersesForChapter(bookName, chapterNum);
                } else {
                    loadVersesForChapter(bookName, chapterNum);
                }
            }
        });
    }

    $(document.body).on('click', '.bible-control-button', function(event) {
        const $button = $(this);
        let $contentArea = $button.closest('.bible-content-area, .bible-search-results, .random-verse-widget, .daily-verse-widget, #bible-container, .bible-single-verse-container');
        if (!$contentArea.length) $contentArea = $('body');
        const action = $button.data('action') || $button.attr('id');

        if (!$(this).hasClass('ajax-nav-link') && !$(this).hasClass('download-image-button') &&
            ['toggle-tashkeel', 'increase-font', 'decrease-font', 'dark-mode-toggle', 'read-aloud', 'generate-image'].includes(action)) {
            event.preventDefault();
        }
        let $textContainer = $contentArea.find('.verses-text-container, .verse-text-container').first();
        if (!$textContainer.length && ($contentArea.is('.verse-text') || $contentArea.is('.random-verse') || $contentArea.is('.daily-verse'))) { 
            $textContainer = $contentArea; 
        } else if (!$textContainer.length) { 
            $textContainer = $contentArea; 
        }

        switch (action) {
            case 'toggle-tashkeel':
                const $verseTexts = $textContainer.find('.verse-text .text-content');
                if ($verseTexts.length > 0) {
                    $verseTexts.each(function() {
                        const $textContentSpan = $(this);
                        const $verseElement = $textContentSpan.closest('.verse-text');
                        const originalText = $verseElement.data('original-text');
                        if (typeof originalText === 'undefined') return;
                        const currentText = $textContentSpan.text();
                        const tashkeelRemovedText = removeArabicTashkeel(originalText);
                        if (currentText.replace(/\s+/g, '') === originalText.replace(/\s+/g, '')) {
                            $textContentSpan.text(tashkeelRemovedText);
                            $button.find('.label').text(bibleFrontend.show_tashkeel_label || 'إظهار التشكيل');
                        } else {
                            $textContentSpan.text(originalText);
                            $button.find('.label').text(bibleFrontend.hide_tashkeel_label || 'إلغاء التشكيل');
                        }
                    });
                }
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
            case 'dark-mode-toggle': toggleDarkMode(); break;
            case 'read-aloud': handleReadAloud($contentArea, $button); break;
            case 'generate-image':
                const verseText = $button.data('verse-text');
                const verseReference = $button.data('verse-reference');
                const $controlsWrapper = $button.closest('.bible-image-generator-controls, .bible-controls-wrapper');
                const $fontSelect = $controlsWrapper.find('select[id^="bible-image-font-select"]');
                const $bgSelect = $controlsWrapper.find('select[id^="bible-image-bg-select"]');

                const selectedFont = $fontSelect.length ? $fontSelect.val() : Object.keys(IMAGE_FONTS_DATA)[0];
                const selectedBackground = $bgSelect.length ? $bgSelect.val() : Object.keys(IMAGE_BACKGROUNDS_DATA)[0];

                if (verseText && verseReference) {
                    const $imageContainer = $contentArea.find('#verse-image-container');
                    if ($imageContainer.length && IMAGE_GENERATOR_STRINGS) {
                        $imageContainer.html(`<p class="bible-loading-message">${IMAGE_GENERATOR_STRINGS.generating_image || 'جارٍ إنشاء الصورة...'}</p>`);
                        setTimeout(() => generateVerseImage(verseText, verseReference, $imageContainer, selectedFont, selectedBackground), 50);
                    } else if (!$imageContainer.length) {
                        console.warn('Image container #verse-image-container not found in content area.');
                    }
                }
                break;
        }
    });

    window.addEventListener('popstate', function(event) {
        if (!$bibleContentContainer.length && !$('.random-verse-widget').length && !$('.daily-verse-widget').length && !$('.bible-single-verse-container').length) return;
        initializeSelectors(); 

        const state = event.state;
        if (state && state.path) {
            const fullPathFromState = state.path;
            const urlObject = new URL(fullPathFromState);
            const testamentFromState = state.testament || DEFAULT_TESTAMENT_VIEW; 
            const basePathForExtraction = new URL(BASE_URL).pathname;
            let relativePathForExtraction = urlObject.pathname;
            if (relativePathForExtraction.startsWith(basePathForExtraction)) {
                relativePathForExtraction = relativePathForExtraction.substring(basePathForExtraction.length);
            }
            const pathSegmentsFromState = relativePathForExtraction.replace(/\/$/, "").split('/').filter(s => s.length > 0);
            const bookNameToLoad = state.book || null;
            const chapterFromState = pathSegmentsFromState.length > (bookNameToLoad ? 0 : -1) ? pathSegmentsFromState[(bookNameToLoad ? 1 : 0)] : null;

            if ($testamentSelect && $testamentSelect.length && $testamentSelect.val() !== testamentFromState) {
                $testamentSelect.val(testamentFromState);
            }
            updateBookDropdown(testamentFromState, bookNameToLoad, chapterFromState, false);
        } else if (window.location.href.replace(/\/$/, '').replace(/\?.*$/,'') === BASE_URL.replace(/\/$/, '')) {
            resetVersesDisplay();
            if($testamentSelect && $testamentSelect.length) $testamentSelect.val(DEFAULT_TESTAMENT_VIEW);
            if($bookSelect && $bookSelect.length) $bookSelect.val('');
            if($chapterSelect && $chapterSelect.length) $chapterSelect.empty().append(`<option value="">${BIBLE_STRINGS.select_chapter || 'اختر الأصحاح'}</option>`).prop('disabled', true);
            if($testamentSelect && $testamentSelect.length) updateBookDropdown($testamentSelect.val(), null, null, false);
        }
    });
    
    // --- التنفيذ عند تحميل الصفحة ---
    applyDarkModePreference();
    if ($bibleContentContainer.length || $('.random-verse-widget').length || $('.daily-verse-widget').length || $('.bible-single-verse-container').length) {
        initializeSelectors(); 

        if ($bibleContentContainer.length) { 
            const urlParams = new URLSearchParams(window.location.search);
            const testamentFromUrl = urlParams.get('testament');
            const pathSegments = window.location.pathname.replace(/\/$/, "").split('/').filter(segment => segment.length > 0);
            const baseSegments = BASE_URL.replace(/\/$/, "").split('/').filter(segment => segment.length > 0);
            let isBiblePage = true;
            for(let i=0; i < baseSegments.length; i++){ if(pathSegments[i] !== baseSegments[i]){ isBiblePage = false; break; } }
            
            let initialBookNameFromData = ($bookSelect && $bookSelect.length) ? $bookSelect.data('initial-book') : null;
            let initialChapterNumFromData = ($chapterSelect && $chapterSelect.length) ? $chapterSelect.data('initial-chapter') : null;

            let initialTestament = DEFAULT_TESTAMENT_VIEW;
            if (testamentFromUrl && TESTAMENTS_LABELS_FROM_PHP.hasOwnProperty(testamentFromUrl)) {
                initialTestament = testamentFromUrl;
            } else if ($testamentSelect && $testamentSelect.length && $testamentSelect.find('option[value="' + $testamentSelect.val() + '"]').length) {
                initialTestament = $testamentSelect.val();
            }
            if($testamentSelect && $testamentSelect.length) $testamentSelect.val(initialTestament);

            const isContentAlreadyLoadedByPHP = ($versesDisplay && $versesDisplay.length && $versesDisplay.find('.verse-text').length > 0);

            if (isContentAlreadyLoadedByPHP && initialBookNameFromData && initialChapterNumFromData) {
                const testamentOfLoadedBook = ($bookSelect && $bookSelect.length) ? $bookSelect.data('current-testament') : null;
                if (testamentOfLoadedBook && $testamentSelect && $testamentSelect.length) {
                    initialTestament = testamentOfLoadedBook;
                    $testamentSelect.val(initialTestament);
                }
                updateBookDropdown(initialTestament, initialBookNameFromData, initialChapterNumFromData, true);
                const currentH1Text = ($mainPageTitleElement && $mainPageTitleElement.length) ? $mainPageTitleElement.text() : (initialBookNameFromData + ' ' + initialChapterNumFromData);
                const currentMetaDesc = $('meta[name="description"]').attr('content');
                updatePageDetails(currentH1Text, currentMetaDesc, initialBookNameFromData, initialChapterNumFromData, initialTestament);
            } else if (isBiblePage && pathSegments.length > baseSegments.length) {
                const bookSlugFromUrl = decodeURIComponent(pathSegments[baseSegments.length]);
                const chapterFromUrl = (pathSegments.length > baseSegments.length + 1) ? pathSegments[baseSegments.length + 1] : null;
                updateBookDropdown(initialTestament, initialBookNameFromData || bookSlugFromUrl, chapterFromUrl, true);
            }
            else {
                updateBookDropdown(initialTestament, initialBookNameFromData, initialChapterNumFromData, true);
                if ($versesDisplay && $versesDisplay.length && $versesDisplay.find('.verse-text').length === 0 && $versesDisplay.find('.bible-select-prompt').length === 0){
                     resetVersesDisplay();
                }
            }
        }
    }
});
