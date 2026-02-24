document.addEventListener('DOMContentLoaded', () => {
    const conversationList = document.getElementById('m-conversation-list');
    const messagesContainer = document.getElementById('m-messages-container');
    const searchInput = document.getElementById('m-search-input');
    const statusFilter = document.getElementById('m-status-filter');
    const currentConversationTitle = document.getElementById('m-current-conversation-title');
    const loadingConversations = document.getElementById('m-loading-conversations');

    let activeConversationId = null;
    const CONVERSATION_API_URL = '../../b/m/fetchConv.php';
    const MESSAGES_API_URL = '../../b/m/fetchM.php';

    // Helper to format date/time
    const formatTime = (isoString) => {
        const date = new Date(isoString);
        return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    };

    // Helper to fetch conversations
    const fetchConversations = async () => {
        conversationList.innerHTML = '';
        if (loadingConversations) loadingConversations.style.display = 'block';

        const status = statusFilter.value;
        const search = searchInput.value;
        let url = CONVERSATION_API_URL;
        const params = new URLSearchParams();

        if (status) {
            params.append('status', status);
        }
        if (search) {
            params.append('search', search);
        }

        if (params.toString()) {
            url += '?' + params.toString();
        }

        try {
            const response = await fetch(url);
            const conversations = await response.json();

            if (loadingConversations) loadingConversations.style.display = 'none';

            if (conversations.length === 0) {
                conversationList.innerHTML = '<p class="m-select-placeholder">No conversations found.</p>';
                return;
            }

            conversations.forEach(conv => {
                const item = document.createElement('div');
                item.classList.add('m-conversation-item');
                if (conv.conversation_id == activeConversationId) {
                    item.classList.add('m-active');
                }
                item.dataset.conversationId = conv.conversation_id;
                const statusText = conv.status === 'not_done' ? 'Active' : 'Inactive';
                const statusClass = `m-status-${conv.status}`;
                // Store full names in data attributes
                item.dataset.userName = `${conv.user_fname} ${conv.user_lname}`;
                item.dataset.staffName = `${conv.staff_fname} ${conv.staff_lname}`;

                item.innerHTML = `
                    <div class="m-conversation-info">
                        <div class="m-conversation-name">${conv.user_fname} ${conv.user_lname} <span style="color:#666; font-weight:normal;">(Staff: ${conv.staff_fname})</span></div>
                        <div class="m-conversation-last-message">${conv.last_message_content || 'No messages yet'}</div>
                    </div>
                    <span class="m-status ${statusClass}">${statusText}</span>
                `;

                item.addEventListener('click', () => {
                    // Remove active class from all items
                    document.querySelectorAll('.m-conversation-item').forEach(i => i.classList.remove('m-active'));
                    // Add active class to the clicked item
                    item.classList.add('m-active');

                    activeConversationId = conv.conversation_id;

                    // Pass full names to fetchMessages
                    fetchMessages(conv.conversation_id, item.dataset.userName, item.dataset.staffName);
                });

                conversationList.appendChild(item);
            });

        } catch (error) {
            console.error('Error fetching conversations:', error);
            if (loadingConversations) loadingConversations.style.display = 'none';
            conversationList.innerHTML = '<p class="m-select-placeholder">Failed to load conversations.</p>';
        }
    };

    // Helper to fetch messages
    const fetchMessages = async (conversationId, userName, staffName) => {
        messagesContainer.innerHTML = '<p class="m-select-placeholder">Loading messages...</p>';

        // Update the header to show both the user and the staff member
        currentConversationTitle.innerHTML = `Customer: ${userName} is talking with Staff: ${staffName}`;

        try {
            const response = await fetch(`${MESSAGES_API_URL}?conversation_id=${conversationId}`);
            const messages = await response.json();

            messagesContainer.innerHTML = ''; // Clear loading message

            if (messages.length === 0) {
                messagesContainer.innerHTML = '<p class="m-select-placeholder">Start a conversation.</p>';
                return;
            }

            messages.forEach(msg => {
                const bubble = document.createElement('div');
                bubble.classList.add('m-message-bubble');
                // sender_type is 'user' or 'staff'
                const senderClass = msg.sender_type === 'user' ? 'm-message-user' : 'm-message-staff';
                bubble.classList.add(senderClass);

                // Add message content and timestamp
                bubble.innerHTML = `
                    ${msg.content}
                    <span class="m-message-time">${formatTime(msg.sent_at)}</span>
                `;
                messagesContainer.appendChild(bubble);
            });

            // Scroll to the bottom of the messages container
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

        } catch (error) {
            console.error('Error fetching messages:', error);
            messagesContainer.innerHTML = '<p class="m-select-placeholder">Failed to load messages.</p>';
        }
    };

    // Initial load
    fetchConversations();

    // Event Listeners for Filtering and Searching
    statusFilter.addEventListener('change', fetchConversations);

    // Debounce the search input to avoid too many requests
    let searchTimeout;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(fetchConversations, 300); // Wait 300ms after user stops typing
    });

});