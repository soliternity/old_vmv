// chat.js
console.log("Chat script loaded");

const convListElem = document.getElementById("convList");
const msgHeaderElem = document.getElementById("msgHeader");
const msgListElem = document.getElementById("msgList");
const msgForm = document.getElementById("msgForm");
const msgInput = document.getElementById("msgInput");
const sendBtn = document.getElementById("sendBtn");

// Modal elements
const confirmOverlay = document.getElementById("confirmOverlay");
const confirmYesBtn = document.getElementById("confirmYes");
const confirmNoBtn = document.getElementById("confirmNo");

let conversations = [];
let activeConvId = null;
let autoRefreshInterval = null;

// Fetch conversations from backend
async function fetchConversations() {
  try {
    const res = await fetch("../../b/chat/viewConversations.php");
    const data = await res.json();
    conversations = data.conversations || [];
    renderConversationList();
  } catch (error) {
    console.error("Failed to fetch conversations:", error);
  }
}

// Fetch messages for a conversation
async function fetchMessages(conversationId) {
  try {
    const res = await fetch(
      `../../b/chat/viewConversationMessages.php?conversation_id=${conversationId}`
    );
    const data = await res.json();
    return data.messages || [];
  } catch (error) {
    console.error("Failed to fetch messages:", error);
    return [];
  }
}

// Render conversation list
function renderConversationList() {
  convListElem.innerHTML = "";
  conversations.forEach((conv) => {
    const div = document.createElement("div");
    div.className =
      "conversation-item" +
      (conv.conversation_id === activeConvId ? " active" : "");
    div.textContent = `${conv.app_user_name}`;
    div.tabIndex = 0;
    div.setAttribute("role", "button");
    div.setAttribute(
      "aria-pressed",
      conv.conversation_id === activeConvId ? "true" : "false"
    );
    div.addEventListener("click", () =>
      setActiveConversation(conv.conversation_id, conv.app_user_name)
    );
    div.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        setActiveConversation(conv.conversation_id, conv.app_user_name);
      }
    });
    convListElem.appendChild(div);
  });
}

// Set active conversation and render messages
async function setActiveConversation(id, participantName) {
  activeConvId = id;
  renderConversationList();

  msgHeaderElem.textContent = `Chat with ${participantName}`;
  msgForm.style.display = "flex";
  msgInput.value = "";
  sendBtn.disabled = true;
  msgInput.focus();

  const messages = await fetchMessages(id);
  renderMessages(messages);

  if (autoRefreshInterval) clearInterval(autoRefreshInterval);

  autoRefreshInterval = setInterval(async () => {
    const newMessages = await fetchMessages(activeConvId);
    renderMessages(newMessages);
  }, 5000);
}

// Render messages into message list
function renderMessages(messages) {
  msgListElem.innerHTML = "";
  if (messages.length === 0) {
    msgListElem.innerHTML =
      '<p style="text-align: center; color: #888">No messages in this conversation.</p>';
    return;
  }

  messages.forEach((msg) => {
    const messageContainer = document.createElement("div");
    messageContainer.className =
      "message-container " +
      (msg.sender_type === "staff" ? "user-message-container" : "peer-message-container");

    const messageDiv = document.createElement("div");
    messageDiv.className =
      "message " + (msg.sender_type === "staff" ? "msg-user" : "msg-peer");
    messageDiv.textContent = msg.content;

    const timeSpan = document.createElement("span");
    timeSpan.className = "timestamp";
    const date = new Date(msg.sent_at);
    timeSpan.textContent = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    messageContainer.appendChild(messageDiv);
    messageContainer.appendChild(timeSpan);

    msgListElem.appendChild(messageContainer);
  });
  msgListElem.scrollTop = msgListElem.scrollHeight;
}

// Enable/disable send button
msgInput.addEventListener("input", () => {
  sendBtn.disabled = msgInput.value.trim() === "";
});

msgForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  const text = msgInput.value.trim();
  if (!text || !activeConvId) return;

  try {
    const res = await fetch("../../b/chat/sendMessage.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        conversation_id: activeConvId,
        content: text,
      }),
      credentials: "include"
    });

    const data = await res.json();
    if (data.success) {
      const newMessages = await fetchMessages(activeConvId);
      renderMessages(newMessages);
    } else {
      showModal("Failed to send message", data.error || "Please try again.", false);
    }
  } catch (err) {
    console.error("Send error:", err);
    showModal("Error", "Error sending message.", false);
  }

  msgInput.value = "";
  sendBtn.disabled = true;
});

// Function to show the modal (FIXED: ensures only one OK button is present)
function showModal(title, message, isConfirm) {
  document.getElementById("modalTitle").textContent = title;
  document.getElementById("modalMessage").textContent = message;
  confirmOverlay.style.display = "flex";

  // Remove existing temporary OK buttons before configuring the modal
  document.querySelectorAll('.modal-buttons button[data-temp="true"]').forEach(btn => btn.remove());
  
  if (isConfirm) {
    confirmYesBtn.style.display = "inline-block";
    confirmNoBtn.style.display = "inline-block";
  } else {
    confirmYesBtn.style.display = "none";
    confirmNoBtn.style.display = "none";
    
    // Add a simple OK button if it's just an info message
    const okBtn = document.createElement('button');
    okBtn.textContent = 'OK';
    okBtn.setAttribute('data-temp', 'true'); // Mark as temporary
    okBtn.onclick = () => confirmOverlay.style.display = "none";
    document.querySelector('.modal-buttons').appendChild(okBtn);
  }
}

// Function to handle the "Mark as Done" action
async function handleMarkAsDone() {
  if (!activeConvId) return;

  try {
    const res = await fetch("../../b/chatfinishConversation.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ conversation_id: activeConvId }),
    });

    const data = await res.json();
    if (data.success) {
      showModal("Success", "Conversation marked as done.", false);
      await fetchConversations();
      msgHeaderElem.textContent = "Select a conversation";
      msgListElem.innerHTML =
        '<p style="text-align: center; color: #888">Please select a conversation to see messages.</p>';
      msgForm.style.display = "none";
      if (autoRefreshInterval) clearInterval(autoRefreshInterval);
      activeConvId = null;
    } else {
      showModal("Error", data.error || "Failed to mark as done.", false);
    }
  } catch (err) {
    console.error(err);
    showModal("Error", "Error marking conversation as done.", false);
  }
}


confirmYesBtn.addEventListener("click", () => {
  confirmOverlay.style.display = "none";
  handleMarkAsDone();
});

confirmNoBtn.addEventListener("click", () => {
  confirmOverlay.style.display = "none";
});

// OPTIONAL: Clear polling if closes UI or navigates
window.addEventListener("beforeunload", () => {
  if (autoRefreshInterval) clearInterval(autoRefreshInterval);
});


// ----------------------------------------------------------------------
// --- NEW SEARCH FUNCTIONALITY LOGIC ---
// ----------------------------------------------------------------------

// NEW: Search Modal elements (assuming these IDs exist in index.html/chat.css)
const searchOverlay = document.getElementById("searchOverlay");
const closeSearchModal = document.getElementById("closeSearchModal");
const searchServiceBtn = document.getElementById("searchServiceBtn");
const searchPartBtn = document.getElementById("searchPartBtn");
const serviceTabBtn = document.getElementById("serviceTabBtn");
const partTabBtn = document.getElementById("partTabBtn");
const serviceSearchInput = document.getElementById("serviceSearchInput");
const partSearchInput = document.getElementById("partSearchInput");
const serviceResults = document.getElementById("serviceResults");
const partResults = document.getElementById("partResults");

// Debounce utility function to limit API calls while typing
function debounce(func, delay) {
  let timeoutId;
  return (...args) => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
      func.apply(this, args);
    }, delay);
  };
}


/**
 * Shows the search modal and activates the specified tab.
 * @param {string} tabId - 'serviceSearch' or 'partSearch'.
 */
function showSearchModal(tabId) {
  // Reset previous search results and input
  serviceSearchInput.value = "";
  partSearchInput.value = "";
  serviceResults.innerHTML =
    '<p class="search-placeholder">Start typing to search for services.</p>';
  partResults.innerHTML =
    '<p class="search-placeholder">Start typing to search for parts.</p>';

  // Set active tab
  const allTabs = document.querySelectorAll(".search-tabs .tab-btn");
  const allContent = document.querySelectorAll(".search-tab-content");
  
  allTabs.forEach((btn) => btn.classList.remove("active"));
  allContent.forEach((content) => content.classList.remove("active"));

  if (tabId === "serviceSearch") {
    document.getElementById("serviceTabBtn").classList.add("active");
    document.getElementById("serviceSearch").classList.add("active");
    // Ensure the focus is on the correct input when the modal opens
    setTimeout(() => document.getElementById("serviceSearchInput").focus(), 0);
  } else if (tabId === "partSearch") {
    document.getElementById("partTabBtn").classList.add("active");
    document.getElementById("partSearch").classList.add("active");
    // Ensure the focus is on the correct input when the modal opens
    setTimeout(() => document.getElementById("partSearchInput").focus(), 0);
  }
  
  searchOverlay.style.display = "flex";
}

function hideSearchModal() {
  searchOverlay.style.display = "none";
}

/**
 * Performs the AJAX search for services or parts.
 * @param {string} query - The search query string.
 * @param {string} type - 'service' or 'part'.
 */
async function performSearch(query, type) {
  const resultsElem = type === "service" ? serviceResults : partResults;
  
  if (query.length < 2) {
    resultsElem.innerHTML = `<p class="search-placeholder">Start typing to search for ${type}s.</p>`;
    return;
  }
  
  resultsElem.innerHTML = '<p class="search-placeholder">Searching...</p>';

  // Using the specified endpoints
  const endpoint =
    type === "service"
      ? "../../b/chat/fetchSvc.php" 
      : "../../b/chat/fetchParts.php";

  try {
    const res = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`);
    const data = await res.json();
    renderResults(data, type);
  } catch (error) {
    console.error(`Failed to fetch ${type}s:`, error);
    resultsElem.innerHTML = `<p class="search-placeholder" style="color: red;">Error searching for ${type}s.`;
  }
}

/**
 * Renders the search results and attaches the click-to-paste event listener.
 * @param {Array<Object>} results - The array of services or parts.
 * @param {string} type - 'service' or 'part'.
 */
function renderResults(results, type) {
  const resultsElem = type === "service" ? serviceResults : partResults;
  resultsElem.innerHTML = "";

  if (!results || results.length === 0) {
    resultsElem.innerHTML = `<p class="search-placeholder">No ${type}s found.</p>`;
    return;
  }

  results.forEach((item) => {
    const itemElem = document.createElement("div");
    itemElem.classList.add("search-result-item");
    
    let metaHTML = '';
    if (type === "service") {
      // Fields: min_cost, max_cost, min_hours, max_hours
      metaHTML = `<div class="result-cost-hours">Cost: ₱${item.min_cost} - ₱${item.max_cost} | Time: ${item.min_hours}h - ${item.max_hours}h</div>`;
    } else if (type === "part") {
      // Fields: price, stock
      metaHTML = `<div class="result-cost-hours">Price: ₱${item.price}</div>`;
    }
    
    itemElem.innerHTML = `
      <div class="result-name">${item.name}</div>
      ${metaHTML}
      <div class="result-description">${item.description}</div>
    `;
    
    // Click listener to auto-paste the details --- MODIFIED FOR BETTER SPACING ---
    itemElem.addEventListener("click", () => {
      const contentPrefix = type === 'service' ? 'Service' : 'Part';
      let metaDetails = '';

      if (type === 'service') {
          // Add extra newline before details section
          metaDetails = `\n\nCost Range: ₱${item.min_cost} - ₱${item.max_cost}\nHour Range: ${item.min_hours}h - ${item.max_hours}h`;
      } else if (type === 'part') {
          // Add extra newline before details section
          metaDetails = `\n\nPrice: ₱${item.price}`;
      }

      // Final message format: [Type: Name]\n\n[Ranges/Details]\n\nDescription: ...
      const messageContent = `[${contentPrefix}: ${item.name}]${metaDetails}\n\nDescription: ${item.description}`;
      
      // Auto-paste to message input
      msgInput.value = messageContent;
      // Manually trigger the input event to re-enable the send button
      msgInput.dispatchEvent(new Event('input'));
      
      hideSearchModal();
      msgInput.focus();
    });
    
    resultsElem.appendChild(itemElem);
  });
}

// Event Listeners for opening/closing the modal
searchServiceBtn.addEventListener("click", () => showSearchModal("serviceSearch"));
searchPartBtn.addEventListener("click", () => showSearchModal("partSearch"));
closeSearchModal.addEventListener("click", hideSearchModal);


// Tab switching logic for the modal
document.querySelectorAll("#searchOverlay .tab-btn").forEach((btn) => {
  btn.addEventListener("click", function () {
    const targetId = this.getAttribute("data-target");
    showSearchModal(targetId); // Re-use showModal to handle tab change and reset
  });
});


// Debounced handlers for search inputs
const debouncedServiceSearch = debounce((e) => {
  performSearch(e.target.value, "service");
}, 300);

const debouncedPartSearch = debounce((e) => {
  performSearch(e.target.value, "part");
}, 300);

serviceSearchInput.addEventListener("keyup", debouncedServiceSearch);
partSearchInput.addEventListener("keyup", debouncedPartSearch);


// Start
fetchConversations();