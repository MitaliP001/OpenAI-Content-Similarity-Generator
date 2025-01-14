jQuery(document).ready(function($) {
    const wordLimit = 800;

    $('.generative-ai-default-prompt').each(function() {
        $(this).on('input', function() {
            let words = $(this).val().split(/\s+/).filter(word => word.length > 0);
            if (words.length > wordLimit) {
                $(this).val(words.slice(0, wordLimit).join(' '));
            }
        });

        $(this).on('keydown', function(event) {
            let words = $(this).val().split(/\s+/).filter(word => word.length > 0);
            if (words.length >= wordLimit && event.key.length === 1 && event.key !== 'Backspace' && event.key !== 'Delete') {
                event.preventDefault();
            }
        });
    });
	
});
