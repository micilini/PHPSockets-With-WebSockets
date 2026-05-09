const state = {
  socket: null,
  currentUser: null,
  users: new Map(),
  activeConversationId: 'global',
  conversations: new Map(),
  messagesByConversation: new Map(),
  typingUsersByConversation: new Map(),
  typingTimers: new Map(),
  pendingMessages: new Map(),
  pendingMessageConversations: new Map(),
  messageElements: new Map(),
  messageReadBy: new Map(),
  isTyping: false,
  typingStopTimer: null,
  lastTypingStartSentAt: 0,
  typingHeartbeatMs: 1000,
  typingIdleStopMs: 1400,
};

state.conversations.set('global', {
  id: 'global',
  type: 'global',
  title: 'Global Room',
  subtitle: 'Everyone online',
  targetUserId: null,
  roomId: 'global',
});

const elements = {
  alertBox: document.getElementById('alertBox'),
  chatPanel: document.getElementById('chatPanel'),
  connectionStatus: document.getElementById('connectionStatus'),
  conversationEyebrow: document.getElementById('conversationEyebrow'),
  conversationTitle: document.getElementById('conversationTitle'),
  currentDisplayName: document.getElementById('currentDisplayName'),
  displayNameInput: document.getElementById('displayNameInput'),
  globalRoomButton: document.getElementById('globalRoomButton'),
  joinButton: document.getElementById('joinButton'),
  joinForm: document.getElementById('joinForm'),
  loginPanel: document.getElementById('loginPanel'),
  messageForm: document.getElementById('messageForm'),
  messageInput: document.getElementById('messageInput'),
  messagesList: document.getElementById('messagesList'),
  onlineCount: document.getElementById('onlineCount'),
  serverUrlInput: document.getElementById('serverUrlInput'),
  typingIndicator: document.getElementById('typingIndicator'),
  usersList: document.getElementById('usersList'),
};

elements.joinForm.addEventListener('submit', (event) => {
  event.preventDefault();

  const displayName = elements.displayNameInput.value.trim();
  const serverUrl = elements.serverUrlInput.value.trim();

  if (!displayName || !serverUrl) {
    showAlert('Display name and WebSocket server URL are required.', 'danger');
    return;
  }

  connect(serverUrl, displayName);
});

elements.globalRoomButton.addEventListener('click', () => {
  setActiveConversation('global');
});

elements.messageForm.addEventListener('submit', (event) => {
  event.preventDefault();

  const text = elements.messageInput.value.trim();
  const conversation = state.conversations.get(state.activeConversationId);

  if (!text) {
    stopTyping();
    return;
  }

  if (!conversation) {
    showAlert('Choose a conversation before sending a message.', 'warning');
    return;
  }

  const clientMessageId = createClientMessageId();

  clearLocalTypingStateBeforeSend();
  addPendingOwnMessage(text, clientMessageId, conversation.id);

  if (conversation.type === 'global') {
    sendEnvelope('message.global', { text, clientMessageId });
  } else {
    sendEnvelope('message.direct', {
      toUserId: conversation.targetUserId,
      text,
      clientMessageId,
    });
  }

  elements.messageInput.value = '';
  elements.messageInput.focus();
});

elements.messageInput.addEventListener('input', () => {
  handleTypingInput();
});

elements.messageInput.addEventListener('blur', () => {
  stopTyping();
});

window.addEventListener('beforeunload', () => {
  stopTyping();

  if (state.socket && state.socket.readyState === WebSocket.OPEN) {
    state.socket.close();
  }
});

renderEmptyMessages();
renderTypingIndicator();
renderConversationHeader();
setStatus('Disconnected', 'offline');

function connect(serverUrl, displayName) {
  disconnect();
  clearAlert();
  setStatus('Connecting', 'connecting');
  setJoinFormEnabled(false);

  try {
    state.socket = new WebSocket(serverUrl);
  } catch (error) {
    setJoinFormEnabled(true);
    setStatus('Disconnected', 'offline');
    showAlert('Invalid WebSocket server URL.', 'danger');
    return;
  }

  state.socket.addEventListener('open', () => {
    sendEnvelope('auth.join', { displayName });
  });

  state.socket.addEventListener('message', (event) => {
    handleServerMessage(event.data);
  });

  state.socket.addEventListener('close', () => {
    const hadCurrentUser = Boolean(state.currentUser);

    setStatus('Disconnected', 'offline');

    if (hadCurrentUser) {
      showAlert('Connection closed. Start the server again and re-enter the chat.', 'warning');
      resetToLogin(false);
      return;
    }

    resetToLogin(true);
  });

  state.socket.addEventListener('error', () => {
    setStatus('Connection error', 'offline');

    if (!state.currentUser) {
      setJoinFormEnabled(true);
    }

    showAlert('Could not connect to the WebSocket server.', 'danger');
  });
}

function disconnect() {
  if (
    state.socket &&
    (state.socket.readyState === WebSocket.OPEN || state.socket.readyState === WebSocket.CONNECTING)
  ) {
    state.socket.close();
  }

  state.socket = null;
}

function handleServerMessage(rawMessage) {
  let envelope;

  try {
    envelope = JSON.parse(rawMessage);
  } catch (error) {
    showAlert('The server sent an invalid JSON message.', 'danger');
    return;
  }

  switch (envelope.type) {
    case 'session.accepted':
      handleSessionAccepted(envelope.payload);
      break;

    case 'session.rejected':
      handleSessionRejected(envelope.payload);
      break;

    case 'presence.snapshot':
      handlePresenceSnapshot(envelope.payload);
      break;

    case 'presence.user_joined':
      handleUserJoined(envelope.payload);
      break;

    case 'presence.user_left':
      handleUserLeft(envelope.payload);
      break;

    case 'message.received':
      handleMessageReceived(envelope.payload);
      break;

    case 'message.read':
      handleMessageRead(envelope.payload);
      break;

    case 'typing.started':
      handleTypingStarted(envelope.payload);
      break;

    case 'typing.stopped':
      handleTypingStopped(envelope.payload);
      break;

    case 'error':
      handleServerError(envelope.payload);
      break;

    default:
      showAlert(`Unsupported server event: ${envelope.type}`, 'warning');
      break;
  }
}

function handleSessionAccepted(payload) {
  const session = payload.session;

  state.currentUser = session;
  state.users.set(session.userId, session);

  elements.currentDisplayName.textContent = session.displayName;
  elements.loginPanel.classList.add('d-none');
  elements.chatPanel.classList.remove('d-none');

  setStatus('Connected', 'online');
  setJoinFormEnabled(true);
  clearAlert();
  setActiveConversation('global');
  renderUsers();

  elements.messageInput.focus();
}

function handleSessionRejected(payload) {
  const message = payload.message || 'Could not enter the chat.';

  disconnect();
  resetToLogin(true);
  showAlert(message, 'danger');
}

function handlePresenceSnapshot(payload) {
  const users = Array.isArray(payload.users) ? payload.users : [];

  state.users.clear();

  for (const user of users) {
    if (user && user.userId) {
      state.users.set(user.userId, user);
      ensureDirectConversationFromUserId(user.userId);
    }
  }

  renderUsers();
}

function handleUserJoined(payload) {
  const user = payload.user;

  if (user && user.userId) {
    state.users.set(user.userId, user);
    ensureDirectConversationFromUserId(user.userId);
    renderUsers();
  }
}

function handleUserLeft(payload) {
  if (payload.userId) {
    state.users.delete(payload.userId);
    clearTypingUserInAllConversations(payload.userId);
    renderUsers();
  }
}

function handleMessageReceived(payload) {
  if (!payload.message) {
    return;
  }

  const message = payload.message;
  const conversationId = conversationIdForMessage(message);
  const conversation = state.conversations.get(conversationId);

  if (conversation && message.roomId && message.roomId !== 'global') {
    conversation.roomId = message.roomId;
  }

  clearTypingUserForConversation(conversationId, message.fromUserId);

  const isOwn = state.currentUser && message.fromUserId === state.currentUser.userId;
  const clientMessageId = message.metadata && message.metadata.clientMessageId;

  if (isOwn && clientMessageId && state.pendingMessages.has(clientMessageId)) {
    state.pendingMessages.delete(clientMessageId);
    updatePendingMessageAsReceived(clientMessageId, message, conversationId);
    return;
  }

  addMessage(message, conversationId);

  if (!isOwn) {
    sendEnvelope('message.read', {
      messageId: message.id,
      roomId: message.roomId || 'global',
    });
  }
}

function handleMessageRead(payload) {
  if (!payload.messageId || !payload.userId) {
    return;
  }

  if (state.currentUser && payload.userId === state.currentUser.userId) {
    return;
  }

  const readBy = state.messageReadBy.get(payload.messageId) || new Map();
  readBy.set(payload.userId, payload.displayName || 'Someone');
  state.messageReadBy.set(payload.messageId, readBy);

  updateMessageStatus(payload.messageId, 'read');
}

function handleTypingStarted(payload) {
  if (!payload.userId || !payload.displayName) {
    return;
  }

  if (state.currentUser && payload.userId === state.currentUser.userId) {
    return;
  }

  const conversationId = conversationIdForTyping(payload);
  const typingUsers = typingUsersForConversation(conversationId);

  typingUsers.set(payload.userId, payload.displayName);

  const timerKey = typingTimerKey(conversationId, payload.userId);
  const currentTimer = state.typingTimers.get(timerKey);

  if (currentTimer) {
    window.clearTimeout(currentTimer);
  }

  const timer = window.setTimeout(() => {
    clearTypingUserForConversation(conversationId, payload.userId);
  }, 4000);

  state.typingTimers.set(timerKey, timer);
  renderTypingIndicator();
}

function handleTypingStopped(payload) {
  if (!payload.userId) {
    return;
  }

  clearTypingUserForConversation(conversationIdForTyping(payload), payload.userId);
}

function handleServerError(payload) {
  const message = payload.message || 'The server returned an error.';

  if (!state.currentUser) {
    disconnect();
    resetToLogin(true);
  }

  showAlert(message, 'danger');
}

function sendEnvelope(type, payload) {
  if (!state.socket || state.socket.readyState !== WebSocket.OPEN) {
    showAlert('WebSocket connection is not open.', 'danger');
    return;
  }

  state.socket.send(JSON.stringify({ type, payload }));
}

function directConversationId(userId) {
  return `direct:${userId}`;
}

function openDirectConversation(userId) {
  const user = state.users.get(userId);

  if (!user || (state.currentUser && userId === state.currentUser.userId)) {
    return;
  }

  ensureDirectConversationFromUserId(userId);
  setActiveConversation(directConversationId(userId));
}

function ensureDirectConversationFromUserId(userId) {
  if (state.currentUser && userId === state.currentUser.userId) {
    return;
  }

  const user = state.users.get(userId);

  if (!user) {
    return;
  }

  const conversationId = directConversationId(userId);

  if (!state.conversations.has(conversationId)) {
    state.conversations.set(conversationId, {
      id: conversationId,
      type: 'direct',
      title: user.displayName,
      subtitle: 'Private conversation',
      targetUserId: userId,
      roomId: null,
    });
  }
}

function setActiveConversation(conversationId) {
  if (!state.conversations.has(conversationId)) {
    return;
  }

  stopTyping();

  state.activeConversationId = conversationId;
  renderConversationHeader();
  renderUsers();
  renderMessages();
  renderTypingIndicator();

  elements.messageInput.focus();
}

function renderConversationHeader() {
  const conversation = state.conversations.get(state.activeConversationId);

  if (!conversation) {
    return;
  }

  elements.conversationTitle.textContent = conversation.title;
  elements.conversationEyebrow.textContent = conversation.type === 'global' ? 'Global room' : 'Private direct';
  elements.globalRoomButton.classList.toggle('conversation-item-active', conversation.id === 'global');
}

function conversationIdForMessage(message) {
  if (message.roomId === 'global') {
    return 'global';
  }

  if (state.currentUser && message.fromUserId !== state.currentUser.userId) {
    ensureDirectConversationFromUserId(message.fromUserId);
    return directConversationId(message.fromUserId);
  }

  const clientMessageId = message.metadata && message.metadata.clientMessageId;
  const pendingConversationId = clientMessageId ? state.pendingMessageConversations.get(clientMessageId) : null;

  if (pendingConversationId) {
    return pendingConversationId;
  }

  return 'global';
}

function conversationIdForTyping(payload) {
  if (payload.scope === 'direct' && payload.userId) {
    ensureDirectConversationFromUserId(payload.userId);
    return directConversationId(payload.userId);
  }

  return 'global';
}

function typingPayloadForActiveConversation() {
  const conversation = state.conversations.get(state.activeConversationId);

  if (!conversation || conversation.type === 'global') {
    return { roomId: 'global' };
  }

  return {
    scope: 'direct',
    toUserId: conversation.targetUserId,
    roomId: conversation.roomId || null,
  };
}

function messagesForConversation(conversationId) {
  if (!state.messagesByConversation.has(conversationId)) {
    state.messagesByConversation.set(conversationId, []);
  }

  return state.messagesByConversation.get(conversationId);
}

function typingUsersForConversation(conversationId) {
  if (!state.typingUsersByConversation.has(conversationId)) {
    state.typingUsersByConversation.set(conversationId, new Map());
  }

  return state.typingUsersByConversation.get(conversationId);
}

function createClientMessageId() {
  return `client_${Date.now()}_${Math.random().toString(16).slice(2)}`;
}

function addPendingOwnMessage(text, clientMessageId, conversationId) {
  const message = {
    id: clientMessageId,
    roomId: conversationId === 'global' ? 'global' : null,
    fromUserId: state.currentUser ? state.currentUser.userId : null,
    kind: 'text',
    body: text,
    metadata: { clientMessageId },
    createdAt: new Date().toISOString(),
    status: 'sent',
  };

  state.pendingMessages.set(clientMessageId, message);
  state.pendingMessageConversations.set(clientMessageId, conversationId);
  addMessage(message, conversationId);
}

function addMessage(message, conversationId) {
  messagesForConversation(conversationId).push(message);

  if (conversationId === state.activeConversationId) {
    appendMessageElement(message);
    elements.messagesList.scrollTop = elements.messagesList.scrollHeight;
  }
}

function updatePendingMessageAsReceived(clientMessageId, message, conversationId) {
  message.status = 'received';

  replaceStoredMessage(conversationId, clientMessageId, message);
  state.pendingMessageConversations.delete(clientMessageId);

  const row = state.messageElements.get(clientMessageId);

  if (!row) {
    if (conversationId === state.activeConversationId) {
      renderMessages();
    }

    return;
  }

  state.messageElements.delete(clientMessageId);
  state.messageElements.set(message.id, row);
  row.dataset.messageId = message.id;

  const status = row.querySelector('.message-status');
  updateStatusElement(status, 'received');
}

function replaceStoredMessage(conversationId, currentMessageId, nextMessage) {
  const messages = messagesForConversation(conversationId);
  const index = messages.findIndex((message) => message.id === currentMessageId);

  if (index === -1) {
    messages.push(nextMessage);
    return;
  }

  messages[index] = nextMessage;
}

function updateMessageStatus(messageId, statusName) {
  for (const messages of state.messagesByConversation.values()) {
    const message = messages.find((item) => item.id === messageId);

    if (message) {
      message.status = statusName;
    }
  }

  const row = state.messageElements.get(messageId);

  if (!row) {
    return;
  }

  const status = row.querySelector('.message-status');

  if (!status) {
    return;
  }

  updateStatusElement(status, statusName);
}

function updateStatusElement(element, statusName) {
  if (!element) {
    return;
  }

  element.classList.remove('message-status-sent', 'message-status-received', 'message-status-read');
  element.classList.add(`message-status-${statusName}`);

  if (statusName === 'sent') {
    element.textContent = '✓';
    element.title = 'Message sent';
    return;
  }

  if (statusName === 'received') {
    element.textContent = '✓✓';
    element.title = 'Message received';
    return;
  }

  element.textContent = '✓✓';
  element.title = 'Message read';
}

function handleTypingInput() {
  if (!state.currentUser) {
    return;
  }

  const text = elements.messageInput.value.trim();

  if (!text) {
    stopTyping();
    return;
  }

  if (!state.isTyping) {
    state.isTyping = true;
    sendTypingStart();
  } else if (Date.now() - state.lastTypingStartSentAt >= state.typingHeartbeatMs) {
    sendTypingStart();
  }

  if (state.typingStopTimer) {
    window.clearTimeout(state.typingStopTimer);
  }

  state.typingStopTimer = window.setTimeout(() => {
    stopTyping();
  }, state.typingIdleStopMs);
}

function sendTypingStart() {
  state.lastTypingStartSentAt = Date.now();
  sendEnvelope('typing.start', typingPayloadForActiveConversation());
}

function stopTyping() {
  if (state.typingStopTimer) {
    window.clearTimeout(state.typingStopTimer);
    state.typingStopTimer = null;
  }

  if (!state.isTyping) {
    return;
  }

  state.isTyping = false;
  state.lastTypingStartSentAt = 0;

  if (state.socket && state.socket.readyState === WebSocket.OPEN && state.currentUser) {
    sendEnvelope('typing.stop', typingPayloadForActiveConversation());
  }
}

function clearLocalTypingStateBeforeSend() {
  if (state.typingStopTimer) {
    window.clearTimeout(state.typingStopTimer);
    state.typingStopTimer = null;
  }

  state.isTyping = false;
  state.lastTypingStartSentAt = 0;
}

function resetToLogin(keepDisplayName) {
  state.currentUser = null;
  state.users.clear();
  state.conversations.clear();
  state.conversations.set('global', {
    id: 'global',
    type: 'global',
    title: 'Global Room',
    subtitle: 'Everyone online',
    targetUserId: null,
    roomId: 'global',
  });
  state.activeConversationId = 'global';
  state.messagesByConversation.clear();
  state.pendingMessages.clear();
  state.pendingMessageConversations.clear();
  state.messageElements.clear();
  state.messageReadBy.clear();
  clearTypingState();

  elements.chatPanel.classList.add('d-none');
  elements.loginPanel.classList.remove('d-none');
  elements.currentDisplayName.textContent = '-';

  if (!keepDisplayName) {
    elements.displayNameInput.value = '';
  }

  setJoinFormEnabled(true);
  renderConversationHeader();
  renderUsers();
  renderMessages();
}

function setJoinFormEnabled(enabled) {
  elements.displayNameInput.disabled = !enabled;
  elements.serverUrlInput.disabled = !enabled;
  elements.joinButton.disabled = !enabled;
  elements.joinButton.textContent = enabled ? 'Enter Chat' : 'Connecting...';
}

function setStatus(label, mode) {
  elements.connectionStatus.textContent = label;
  elements.connectionStatus.classList.remove('status-online', 'status-offline', 'status-connecting');
  elements.connectionStatus.classList.add(`status-${mode}`);
}

function showAlert(message, type) {
  elements.alertBox.textContent = message;
  elements.alertBox.className = `alert app-alert alert-${type}`;
}

function clearAlert() {
  elements.alertBox.textContent = '';
  elements.alertBox.className = 'alert app-alert d-none';
}

function renderUsers() {
  elements.usersList.replaceChildren();
  elements.onlineCount.textContent = String(state.users.size);

  const users = [...state.users.values()]
    .filter((user) => !state.currentUser || user.userId !== state.currentUser.userId)
    .sort((first, second) => first.displayName.localeCompare(second.displayName));

  if (users.length === 0) {
    const empty = document.createElement('div');
    empty.className = 'empty-state';
    empty.textContent = 'No other users online yet.';
    elements.usersList.appendChild(empty);
    return;
  }

  for (const user of users) {
    const item = document.createElement('button');
    item.className = 'user-item';
    item.type = 'button';
    item.classList.toggle('user-item-active', state.activeConversationId === directConversationId(user.userId));
    item.addEventListener('click', () => {
      openDirectConversation(user.userId);
    });

    const avatar = document.createElement('div');
    avatar.className = 'user-avatar';
    avatar.textContent = user.displayName.slice(0, 1).toUpperCase();

    const name = document.createElement('div');
    name.className = 'user-name';
    name.textContent = user.displayName;

    item.appendChild(avatar);
    item.appendChild(name);

    elements.usersList.appendChild(item);
  }
}

function renderMessages() {
  state.messageElements.clear();
  elements.messagesList.replaceChildren();

  const messages = messagesForConversation(state.activeConversationId);

  if (messages.length === 0) {
    renderEmptyMessages();
    return;
  }

  for (const message of messages) {
    appendMessageElement(message);
  }

  elements.messagesList.scrollTop = elements.messagesList.scrollHeight;
}

function renderEmptyMessages() {
  elements.messagesList.replaceChildren();

  const empty = document.createElement('div');
  empty.className = 'empty-state';
  empty.textContent = 'No messages yet. Start the conversation.';

  elements.messagesList.appendChild(empty);
}

function appendMessageElement(message) {
  const empty = elements.messagesList.querySelector('.empty-state');

  if (empty) {
    empty.remove();
  }

  const isOwn = state.currentUser && message.fromUserId === state.currentUser.userId;
  const sender = findDisplayName(message.fromUserId);
  const createdAt = formatTime(message.createdAt);

  const row = document.createElement('div');
  row.className = isOwn ? 'message-row is-own' : 'message-row';
  row.dataset.messageId = message.id;

  const footer = document.createElement('div');
  footer.className = 'message-footer';

  const meta = document.createElement('div');
  meta.className = 'message-meta';
  meta.textContent = `${sender} - ${createdAt}`;

  const status = document.createElement('span');
  status.className = 'message-status';
  updateStatusElement(status, isOwn ? message.status || 'received' : 'received');

  const bubble = document.createElement('div');
  bubble.className = 'message-bubble';
  bubble.textContent = message.body || '';

  footer.appendChild(meta);
  footer.appendChild(status);
  row.appendChild(footer);
  row.appendChild(bubble);

  state.messageElements.set(message.id, row);
  elements.messagesList.appendChild(row);
}

function renderTypingIndicator() {
  const names = [...typingUsersForConversation(state.activeConversationId).values()];

  if (names.length === 0) {
    elements.typingIndicator.textContent = '';
    elements.typingIndicator.classList.add('d-none');
    return;
  }

  elements.typingIndicator.textContent = `${formatTypingNames(names)} ${names.length === 1 ? 'is' : 'are'} typing`;
  elements.typingIndicator.classList.remove('d-none');
}

function formatTypingNames(names) {
  if (names.length === 1) {
    return names[0];
  }

  if (names.length === 2) {
    return `${names[0]} and ${names[1]}`;
  }

  return `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}`;
}

function clearTypingUserForConversation(conversationId, userId) {
  if (!userId) {
    return;
  }

  const timerKey = typingTimerKey(conversationId, userId);
  const timer = state.typingTimers.get(timerKey);

  if (timer) {
    window.clearTimeout(timer);
    state.typingTimers.delete(timerKey);
  }

  typingUsersForConversation(conversationId).delete(userId);
  renderTypingIndicator();
}

function clearTypingUserInAllConversations(userId) {
  for (const conversationId of state.typingUsersByConversation.keys()) {
    clearTypingUserForConversation(conversationId, userId);
  }
}

function clearTypingState() {
  if (state.typingStopTimer) {
    window.clearTimeout(state.typingStopTimer);
    state.typingStopTimer = null;
  }

  for (const timer of state.typingTimers.values()) {
    window.clearTimeout(timer);
  }

  state.typingUsersByConversation.clear();
  state.typingTimers.clear();
  state.isTyping = false;
  state.lastTypingStartSentAt = 0;
  renderTypingIndicator();
}

function typingTimerKey(conversationId, userId) {
  return `${conversationId}:${userId}`;
}

function findDisplayName(userId) {
  const user = state.users.get(userId);

  if (!user) {
    return state.currentUser && userId === state.currentUser.userId ? state.currentUser.displayName : 'Unknown user';
  }

  return user.displayName;
}

function formatTime(value) {
  if (!value) {
    return 'now';
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return 'now';
  }

  return date.toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
  });
}
