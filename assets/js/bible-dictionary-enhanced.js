jQuery(document).ready(function($) {
    'use strict';
    
    // دوال مساعدة للحصول على السفر والأصحاح الحالي
    function getCurrentBookName() {
        const $bookSelect = $('#bible-book-select');
        if ($bookSelect && $bookSelect.length && $bookSelect.val()) {
            return $bookSelect.val();
        }
        
        // محاولة أخرى من عنوان الصفحة أو URL
        const urlPath = window.location.pathname;
        const pathParts = urlPath.split('/').filter(part => part.length > 0);
        
        if (pathParts.length >= 2 && pathParts[0] === 'bible') {
            return decodeURIComponent(pathParts[1]).replace(/-/g, ' ');
        }
        
        return null;
    }
    
    function getCurrentChapterNumber() {
        const $chapterSelect = $('#bible-chapter-select');
        if ($chapterSelect && $chapterSelect.length && $chapterSelect.val()) {
            return parseInt($chapterSelect.val());
        }
        
        // محاولة أخرى من URL
        const urlPath = window.location.pathname;
        const pathParts = urlPath.split('/').filter(part => part.length > 0);
        
        if (pathParts.length >= 3 && pathParts[0] === 'bible') {
            const chapterNum = parseInt(pathParts[2]);
            return isNaN(chapterNum) ? null : chapterNum;
        }
        
        return null;
    }
    
    // تحسين زر معاني كلمات الأصحاح
    $(document).on('click', '[data-action="show-chapter-terms"]', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $contentArea = $button.closest('.bible-content-area, .bible-container, #bible-container').first();
        const $chapterTermsList = $('#chapter-terms-list');
        const $chapterTermsModal = $('#chapter-terms-modal');
        
        if (!$chapterTermsList.length || !$chapterTermsModal.length) {
            console.warn('Chapter terms modal elements not found');
            return;
        }
        
        // أولاً: جمع الكلمات الموجودة محلياً في الصفحة
        const terms = new Map();
        $contentArea.find('a.bible-term').each(function() {
            const termText = $(this).text();
            const termDef = $(this).data('definition');
            if (termText && termDef && !terms.has(termText)) { 
                terms.set(termText, termDef); 
            }
        });

        // إذا وُجدت كلمات محلياً، اعرضها
        if (terms.size > 0) {
            let listHtml = '<dl class="terms-definition-list">';
            const sortedTerms = new Map([...terms.entries()].sort());
            sortedTerms.forEach((def, term) => {
                listHtml += `<dt class="term-name">${term}</dt><dd class="term-definition">${def}</dd>`;
            });
            listHtml += '</dl>';
            $chapterTermsList.html(listHtml);
            $chapterTermsModal.addClass('active');
        } else {
            // إذا لم توجد كلمات محلياً، جرب الحصول عليها من الخادم
            const currentBook = getCurrentBookName();
            const currentChapter = getCurrentChapterNumber();
            
            if (currentBook && currentChapter) {
                $chapterTermsList.html('<div class="loading-message"><i class="fas fa-spinner fa-spin"></i> جاري البحث عن معاني الكلمات...</div>');
                $chapterTermsModal.addClass('active');
                
                const ajaxUrl = (typeof bibleFrontend !== 'undefined' && bibleFrontend.ajax_url) ? bibleFrontend.ajax_url : '/wp-admin/admin-ajax.php';
                const ajaxNonce = (typeof bibleFrontend !== 'undefined' && bibleFrontend.nonce) ? bibleFrontend.nonce : '';
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'bible_get_chapter_terms',
                        book: currentBook,
                        chapter: currentChapter,
                        nonce: ajaxNonce
                    },
                    dataType: 'json',
                    timeout: 10000, // 10 ثواني timeout
                    success: function(response) {
                        if (response.success && response.data && Object.keys(response.data).length > 0) {
                            let listHtml = '<dl class="terms-definition-list">';
                            const sortedTerms = Object.keys(response.data).sort();
                            sortedTerms.forEach(term => {
                                listHtml += `<dt class="term-name">${term}</dt><dd class="term-definition">${response.data[term]}</dd>`;
                            });
                            listHtml += '</dl>';
                            $chapterTermsList.html(listHtml);
                        } else {
                            $chapterTermsList.html(`<div class="no-terms-message">
                                <i class="fas fa-info-circle"></i> 
                                <p>لم يتم العثور على كلمات لها معانٍ في هذا الأصحاح.</p>
                                <p class="note">قد تحتاج إلى إضافة كلمات جديدة إلى القاموس.</p>
                            </div>`);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Ajax error:', status, error);
                        let errorMessage = 'حدث خطأ أثناء جلب معاني الكلمات.';
                        
                        if (status === 'timeout') {
                            errorMessage = 'انتهت مهلة الطلب. حاول مرة أخرى.';
                        } else if (xhr.status === 403) {
                            errorMessage = 'غير مصرح لك بالوصول إلى هذه البيانات.';
                        } else if (xhr.status === 404) {
                            errorMessage = 'لم يتم العثور على الخدمة المطلوبة.';
                        } else if (xhr.status >= 500) {
                            errorMessage = 'خطأ في الخادم. حاول مرة أخرى لاحقاً.';
                        }
                        
                        $chapterTermsList.html(`<div class="error-message">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <p>${errorMessage}</p>
                            <button class="retry-button" onclick="$(this).closest('.bible-controls-wrapper').find('[data-action=\\'show-chapter-terms\\']').click();">
                                <i class="fas fa-redo"></i> إعادة المحاولة
                            </button>
                        </div>`);
                    }
                });
            } else {
                $chapterTermsList.html(`<div class="no-selection-message">
                    <i class="fas fa-info-circle"></i> 
                    <p>يرجى اختيار سفر وأصحاح أولاً لعرض معاني الكلمات.</p>
                </div>`);
                $chapterTermsModal.addClass('active');
            }
        }
    });
    
    // تحسين عرض القاموس مع CSS إضافي
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .terms-definition-list {
                margin: 0;
                padding: 0;
            }
            
            .terms-definition-list .term-name {
                font-weight: bold;
                color: #2563eb;
                margin: 15px 0 5px 0;
                padding: 8px 12px;
                background: #f8fafc;
                border-right: 3px solid #2563eb;
                border-radius: 4px;
            }
            
            .terms-definition-list .term-definition {
                margin: 0 0 10px 20px;
                padding: 8px 12px;
                line-height: 1.6;
                color: #374151;
            }
            
            .loading-message, .error-message, .no-terms-message, .no-selection-message {
                text-align: center;
                padding: 30px 20px;
                color: #6b7280;
            }
            
            .loading-message i, .error-message i, .no-terms-message i, .no-selection-message i {
                font-size: 24px;
                margin-bottom: 10px;
                display: block;
            }
            
            .error-message {
                color: #dc2626;
            }
            
            .error-message i {
                color: #fbbf24;
            }
            
            .retry-button {
                margin-top: 15px;
                padding: 8px 16px;
                background: #2563eb;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            }
            
            .retry-button:hover {
                background: #1d4ed8;
            }
            
            .note {
                font-size: 12px;
                opacity: 0.8;
                margin-top: 10px;
            }
        `)
        .appendTo('head');
});