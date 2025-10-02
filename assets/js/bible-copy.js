// assets/js/bible-copy.js
document.addEventListener('copy', function(event) {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) return;

    const selectedText = selection.toString().trim();
    
    if (selectedText) {
        // محاولة العثور على أقرب عنصر .verse-text أب
        let verseElement = null;
        let currentNode = selection.anchorNode;

        // التحقق إذا كان التحديد ضمن عنصر نصي أو عنصر
        if (currentNode) {
            if (currentNode.nodeType === Node.TEXT_NODE) {
                verseElement = currentNode.parentElement.closest('.verse-text');
            } else if (currentNode.nodeType === Node.ELEMENT_NODE) {
                verseElement = currentNode.closest('.verse-text');
            }
        }
        
        if (verseElement) {
            const verseUrl = verseElement.getAttribute('data-verse-url');
            const originalTextAttr = verseElement.getAttribute('data-original-text'); // النص الأصلي الكامل للآية
            
            // محاولة استخراج المرجع من الرابط الأخير داخل .verse-text
            // نفترض أن الرابط الأخير هو رابط المرجع [سفر اصحاح:آية]
            const referenceLink = verseElement.querySelector('a.verse-reference-link');
            let referenceText = '';
            if (referenceLink && referenceLink.textContent) {
                referenceText = referenceLink.textContent.trim(); // [سفر اصحاح:آية]
            } else {
                // إذا لم يتم العثور على رابط المرجع، حاول بناءه من النص الأصلي إذا كان التحديد جزءاً منه
                // هذا جزء احتياطي وقد لا يكون دقيقاً دائماً
                if (originalTextAttr) {
                    // محاولة استخلاص المرجع من النص الأصلي إذا كان موجوداً بصيغة معروفة
                    // هذا مثال بسيط، قد تحتاج لتحسينه
                    const match = originalTextAttr.match(/\[(.*?)\]$/); // يبحث عن [...] في نهاية النص
                    if (match && match[1]) {
                        referenceText = '[' + match[1] + ']';
                    } else {
                         // إذا لم يوجد مرجع واضح، استخدم اسم السفر والأصحاح والآية من الـ URL إذا أمكن
                        if (verseUrl) {
                            try {
                                const urlParts = new URL(verseUrl).pathname.split('/').filter(Boolean);
                                if (urlParts.length >= 4 && urlParts[0] === 'bible') { // bible/book/chapter/verse
                                    const book = decodeURIComponent(urlParts[1].replace(/-/g, ' '));
                                    const chapter = urlParts[2];
                                    const verseNum = urlParts[3];
                                    referenceText = `[${book} ${chapter}:${verseNum}]`;
                                }
                            } catch (e) { /* تجاهل الخطأ إذا كان الرابط غير صالح */ }
                        }
                    }
                }
            }

            let textToCopy = selectedText;
            if (referenceText) {
                textToCopy += ' ' + referenceText;
            }
            if (verseUrl) {
                textToCopy += '\n' + verseUrl;
            }
            
            // استخدام واجهة برمجة تطبيقات الحافظة إذا كانت متاحة (أكثر حداثة)
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    // تم النسخ بنجاح (اختياري: إظهار رسالة للمستخدم)
                    // console.log('Verse copied to clipboard!');
                }).catch(function(err) {
                    // console.error('Failed to copy verse: ', err);
                    // الرجوع إلى الطريقة القديمة إذا فشلت الطريقة الحديثة
                    fallbackCopyTextToClipboard(textToCopy, event);
                });
            } else {
                // الطريقة القديمة (execCommand)
                fallbackCopyTextToClipboard(textToCopy, event);
            }
            
            event.preventDefault(); // منع عملية النسخ الافتراضية
        }
    }
});

function fallbackCopyTextToClipboard(text, event) {
    if (event.clipboardData && event.clipboardData.setData) {
        event.clipboardData.setData('text/plain', text);
    } else {
        // طريقة احتياطية جداً (قد لا تعمل في جميع المتصفحات الحديثة بسبب قيود الأمان)
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";  // لمنع التمرير عند الإضافة
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
        } catch (err) {
            // console.error('Fallback copy failed: ', err);
        }
        document.body.removeChild(textArea);
    }
}
