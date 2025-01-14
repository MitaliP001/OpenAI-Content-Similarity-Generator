jQuery(document).ready(function($) {
    $('.similar-content-button').on('click', function(e) {
        e.preventDefault();

        var buttonNumber = $(this).data('button-number');
        var buttonLabel = $(this).text();
        var content = $(this).data('content');

        $('#similarContentModal .main-title').text(buttonLabel);
        $('#loader').show(); // Show loader while the request is processed

        // Call the OpenAI API asynchronously
        callOpenAI(content).then(result => {
            $('#loader').hide(); // Hide loader once the response is received
            console.log('Response:', result);
            var formattedResult = formatResult(result);
            $('#similarContentModal .modal-body').html(formattedResult);
            $('#similarContentModal').show(); // Show modal with the result
        }).catch(error => {
            $('#loader').hide(); // Hide loader in case of error
            console.error('Fetch Error:', error);
            alert('Error: Unable to connect to the server. Please try again later.');
        });
    });

    // Close modal functionality
    $(document).on('click', '#similarContentModal .close, .overlay', function(e) {
        if (e.target == this) {
            $('#similarContentModal').hide();
        }
    });

    // Copy functionality
    $(document).on('click', '.copy-button a.copy', function(event) {
        event.preventDefault();
        const popupContent = $('.popup-inner.modal-body').text();
        const mainTitle = $('h2.main-title').text();
        const textToCopy = `${mainTitle}\n${popupContent}`;

        navigator.clipboard.writeText(textToCopy).then(() => {
            const $copyMessage = $(this).siblings('.copy-message');
            $copyMessage.show();
            setTimeout(() => {
                $copyMessage.hide();
            }, 3000);
        }).catch(err => {
            console.error('Failed to copy: ', err);
        });
    });

    // Formatting function for result
    function formatResult(result) {
        var formatted = '<div>';

        if (typeof result === 'string') {
            var lines = result.split('\n');
            var inList = false;

            lines.forEach(function(line) {
                if (line.trim().startsWith('-')) {
                    line = '<li>' + line.trim().substring(1).trim() + '</li>';
                    if (!inList) {
                        formatted += '<ul>' + line;
                        inList = true;
                    } else {
                        formatted += line;
                    }
                } else {
                    if (inList) {
                        formatted += '</ul>';
                        inList = false;
                    }
                    formatted += '<p>' + line + '</p>';
                }
            });

            if (inList) {
                formatted += '</ul>';
            }
        } else if (Array.isArray(result)) {
            result.forEach(function(item) {
                formatted += '<p>' + item + '</p>';
            });
        } else if (typeof result === 'object') {
            for (var key in result) {
                if (result.hasOwnProperty(key)) {
                    formatted += '<h1>' + key + '</h1>';
                    formatted += '<p>' + result[key] + '</p>';
                }
            }
        }

        formatted += '</div>';
        return formatted;
    }

    // Asynchronous OpenAI API call with streaming response handling
    async function callOpenAI(content) {
        const apiKey = document.getElementById('apiKey').value; // Assuming an input for API key
        const popup = document.getElementById('popup');
        const popupContent = document.getElementById('popupContent');

        popupContent.textContent = ''; // Clear previous output
        popup.style.display = 'block'; // Show popup initially

        if (!apiKey || !content) {
            alert('Please enter both API key and content.');
            return;
        }

        const response = await fetch('https://api.openai.com/v1/chat/completions', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${apiKey}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                model: "gpt-4",
                messages: [
                    { role: "system", content: "The AI chatbot serves as an expert business content writer with 15 years of experience in understanding and writing about business issues." },
                    { role: "user", content: content }
                ],
                stream: true
            })
        });

        const reader = response.body.getReader();
        const decoder = new TextDecoder("utf-8");
        let result = '';

        // Stream processing
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            const chunk = decoder.decode(value, { stream: true });
            const jsonChunks = chunk.split('\n\n').filter(c => c.trim() !== ''); // Split on double newline (each chunk)

            jsonChunks.forEach(jsonChunk => {
                if (jsonChunk.startsWith('data: ')) {
                    try {
                        const parsedData = JSON.parse(jsonChunk.substring(6)); // Skip the "data: " prefix
                        const contentDelta = parsedData.choices[0].delta.content || ''; // Extract the content

                        if (contentDelta) {
                            result += contentDelta; // Accumulate the streaming content
                        }
                    } catch (err) {
                        console.error('Error parsing chunk:', err);
                    }
                }
            });
        }

        return result; // Return the accumulated result
    }
});
