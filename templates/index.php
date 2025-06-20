<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Email Sender</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        .preview-container {
            max-height: 400px;
            overflow-y: auto;
        }
        .preview-item {
            border: 1px solid #ddd;
            margin-bottom: 10px;
            padding: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Bulk Email Sender</h1>
        
        <form id="emailForm" class="mb-4">
            <input type="hidden" name="_csrf_token" value="<?= $token->getValue() ?>">
            
            <div class="mb-3">
                <label for="subject" class="form-label">Subject</label>
                <input type="text" class="form-control" id="subject" name="subject" required>
            </div>

            <div class="mb-3">
                <label for="cc" class="form-label">CC (Optional)</label>
                <input type="text" class="form-control" id="cc" name="cc" placeholder="Enter CC email addresses separated by commas">
                <small class="form-text text-muted">Overrides all 'cc' addresses from the Excel file</small>
            </div>

            <div class="mb-3">
                <label for="editor" class="form-label">Email Content</label>
                <div id="editor" style="height: 300px;"></div>
                <input type="hidden" name="body" id="body">
            </div>

            <div class="mb-3">
                <label for="excelFile" class="form-label">Upload Excel File</label>
                <input type="file" class="form-control" id="excelFile" name="excel_file" accept=".xlsx" required>
                <small class="form-text text-muted">First row should contain column headers, including 'email' column</small>
            </div>

            <div class="mb-3">
                <button type="button" class="btn btn-primary" id="previewBtn">Preview Emails</button>
                <button type="button" class="btn btn-success" id="sendBtn" style="display: none;">Send Emails</button>
            </div>
        </form>

        <div id="previewSection" class="preview-container" style="display: none;">
            <h3>Email Previews</h3>
            <div id="previewList"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script>
        const quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link', 'image'],
                    ['clean']
                ]
            }
        });

        let recipients = [];
        const csrfToken = document.querySelector('input[name="_csrf_token"]').value;

        document.getElementById('excelFile').addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('excel_file', file);
            formData.append('_csrf_token', csrfToken);

            try {
                const response = await fetch('/upload-excel', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error || 'Failed to upload file');
                }
                
                const data = await response.json();
                if (data.error) {
                    alert(data.error);
                    return;
                }
                recipients = data.recipients;
                console.log('Recipients loaded:', recipients);
            } catch (error) {
                console.error('Upload error:', error);
                alert('Error uploading file: ' + error.message);
            }
        });

        document.getElementById('previewBtn').addEventListener('click', async function() {
            const requestData = {
                subject: document.getElementById('subject').value,
                cc: document.getElementById('cc').value,
                body: quill.root.innerHTML,
                recipients: recipients
            };

            try {
                const response = await fetch('/preview', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(requestData)
                });
                
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error || 'Failed to generate preview');
                }
                
                const data = await response.json();
                if (data.error) {
                    alert(data.error);
                    return;
                }
                
                const previewList = document.getElementById('previewList');
                previewList.innerHTML = '';
                
                data.previews.forEach(preview => {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'preview-item';
                    const ccText = preview.cc ? `<p><strong>CC:</strong> ${preview.cc}</p>` : '';
                    previewItem.innerHTML = `
                        <h5>To: ${preview.email}</h5>
                        ${ccText}
                        <h6>Subject: ${preview.subject}</h6>
                        <div>${preview.body}</div>
                    `;
                    previewList.appendChild(previewItem);
                });

                document.getElementById('previewSection').style.display = 'block';
                document.getElementById('sendBtn').style.display = 'inline-block';
            } catch (error) {
                console.error('Preview error:', error);
                alert('Error generating preview: ' + error.message);
            }
        });

        document.getElementById('sendBtn').addEventListener('click', async function() {
            if (!confirm('Are you sure you want to send these emails?')) return;

            const sendBtn = document.getElementById('sendBtn');
            const originalText = sendBtn.textContent;
            sendBtn.disabled = true;
            sendBtn.textContent = 'Sending...';

            const requestData = {
                subject: document.getElementById('subject').value,
                cc: document.getElementById('cc').value,
                body: quill.root.innerHTML
            };

            let allResults = {};
            let totalProcessed = 0;
            let totalSuccess = 0;
            let totalErrors = 0;
            let currentRecipients = [...recipients];

            try {
                while (currentRecipients.length > 0) {
                    // Take next 5 recipients
                    const chunkRecipients = currentRecipients.splice(0, 5);
                    
                    const chunkRequestData = {
                        ...requestData,
                        recipients: chunkRecipients
                    };

                    const response = await fetch('/send', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify(chunkRequestData)
                    });
                    
                    let data;
                    const responseText = await response.text();
                    
                    try {
                        data = JSON.parse(responseText);
                    } catch (parseError) {
                        throw new Error('Invalid response from server: ' + responseText);
                    }
                    
                    if (!response.ok) {
                        throw new Error(data.error || 'Failed to send emails');
                    }
                    
                    if (data.error) {
                        alert(data.error);
                        break;
                    }
                    
                    // Merge results
                    Object.assign(allResults, data.results);
                    
                    // Update counters
                    totalProcessed += chunkRecipients.length;
                    Object.values(data.results).forEach(result => {
                        if (result.status === 'success') {
                            totalSuccess++;
                        } else {
                            totalErrors++;
                        }
                    });

                    // Update progress
                    sendBtn.textContent = `Sending... (${totalProcessed}/${recipients.length})`;
                    
                    // Small delay between chunks to avoid overwhelming the server
                    if (currentRecipients.length > 0) {
                        await new Promise(resolve => setTimeout(resolve, 1000));
                    }
                }

                alert(`Emails sent: ${totalSuccess} successful, ${totalErrors} failed (${totalProcessed} total processed)`);
            } catch (error) {
                console.error('Send error:', error);
                alert('Error sending emails: ' + error.message);
            } finally {
                sendBtn.disabled = false;
                sendBtn.textContent = originalText;
            }
        });
    </script>
</body>
</html> 