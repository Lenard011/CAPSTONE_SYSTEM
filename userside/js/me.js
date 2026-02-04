// Get elements
const messageIcon = document.getElementById('messageIcon');
const inboxContainer = document.getElementById('inboxContainer');
const closeInbox = document.getElementById('closeInbox');
const sendMessage = document.getElementById('sendMessage');
const messageInput = document.getElementById('messageInput');
const messageList = document.getElementById('messageList');

// Show inbox when message icon is clicked
messageIcon.addEventListener('click', () => {
    inboxContainer.style.display = 'flex';
});

// Close inbox when close button is clicked
closeInbox.addEventListener('click', () => {
    inboxContainer.style.display = 'none';
});

// Send message when button is clicked
sendMessage.addEventListener('click', () => {
    const messageText = messageInput.value.trim();
    if (messageText) {
        const messageItem = document.createElement('div');
        messageItem.className = 'message-item';
        messageItem.textContent = messageText;
        messageList.appendChild(messageItem);
        messageInput.value = ''; // Clear the input after sending
        messageList.scrollTop = messageList.scrollHeight; // Scroll to the bottom
    }
});