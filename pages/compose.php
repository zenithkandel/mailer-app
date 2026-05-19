<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="compose-page">
        <h2>New Message</h2>
        <input type="hidden" id="csrfToken" value="<?= $csrfToken ?>">

        <form class="compose-form" id="composeForm">
            <div class="form-group">
                <label for="composeTo">To</label>
                <input type="email" id="composeTo" placeholder="recipient@example.com" value="<?= sanitize($reply_to) ?>" required>
            </div>
            <div class="form-group">
                <label for="composeSubject">Subject</label>
                <input type="text" id="composeSubject" placeholder="Subject" value="<?= $is_forward ? 'Fwd: ' . sanitize($reply_subject) : ($reply_id ? 'Re: ' . sanitize($reply_subject) : '') ?>">
            </div>
            <div class="form-group">
                <label for="composeBody">Message</label>
                <textarea id="composeBody" placeholder="Write your message..."></textarea>
            </div>
            <div class="compose-actions">
                <button type="submit" class="btn btn-primary" id="sendBtn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    Send
                </button>
            </div>
        </form>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        const CSRF_TOKEN = document.getElementById('csrfToken').value;

        function toast(msg, type = 'info') {
            const c = document.getElementById('toastContainer');
            const t = document.createElement('div');
            t.className = 'toast ' + type;
            t.textContent = msg;
            c.appendChild(t);
            setTimeout(() => t.remove(), 4000);
        }

        document.getElementById('composeForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('sendBtn');
            const to = document.getElementById('composeTo').value.trim();
            const subject = document.getElementById('composeSubject').value.trim();
            const body = document.getElementById('composeBody').value;

            if (!to || !subject || !body) {
                toast('Please fill in all fields', 'error');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = 'Sending...';

            try {
                const formData = new FormData();
                formData.append('to', to);
                formData.append('subject', subject);
                formData.append('body', body);
                formData.append('csrf_token', CSRF_TOKEN);

                const res = await fetch('../api/send.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: formData
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Failed to send');
                toast('Email sent successfully', 'success');
                document.getElementById('composeForm').reset();
            } catch (err) {
                toast(err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send';
            }
        });
    </script>
</body>
</html>
            