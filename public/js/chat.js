// File: public/js/chat.js
const chatForm = document.getElementById('chatForm');
const userInput = document.getElementById('userInput');
const chatLog = document.getElementById('chatLog');
const clearBtn = document.getElementById('clearBtn');

function appendMessage(role, text) {
    const li = document.createElement('li');
    li.className = role;
    const span = document.createElement('span');
    span.className = 'message';
    span.textContent = text;
    li.appendChild(span);
    chatLog.appendChild(li);
    chatLog.scrollTop = chatLog.scrollHeight;
}

async function sendMessage(message) {
    try {
        const response = await fetch('src/chats.php?action=send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message })
        });
        const data = await response.json();
        if (data.reply) {
            appendMessage('assistant', data.reply);
        } else if (data.error) {
            appendMessage('assistant', `Error: ${data.error}`);
        }
    } catch (e) {
        appendMessage('assistant', `Network error`);
    }
}

chatForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = userInput.value.trim();
    if (!msg) return;
    appendMessage('user', msg);
    userInput.value = '';
    await sendMessage(msg);
});

clearBtn.addEventListener('click', async () => {
    if (!confirm('Clear the entire conversation?')) return;
    try {
        await fetch('src/chats.php?action=clear');
        chatLog.innerHTML = '';
    } catch (_) { /* ignore */ }
});