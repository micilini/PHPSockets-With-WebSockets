const state = {
  socket: null,
  currentUser: null,
  users: new Map(),
  typingUsers: new Map(),
  typingTimers: new Map(),
  isTyping: false,
  typingStopTimer: null,
  lastTypingStartSentAt: 0,
  typingHeartbeatMs: 1000,
  typingIdleStopMs: 1400,
};

const elements = {
  alertBox: document.getElementById('alertBox'),
  chatPanel: document.getElementById('chatPanel'),
  connectionStatus: document.getElementById('connectionStatus'),
  currentDisplayName: document.getElementById('currentDisplayName'),
  displayNameInput: document.getElementById('displayNameInput'),
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

elements.messageForm.addEventListener('submit', (event) => {
  event.preventDefault();

  const text = elements.messageInput.value.trim();

  if (!text) {
    stopTyping();
    return;
  }

  clearLocalTypingStateBeforeSend();
  sendEnvelope('message.global', { text });
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
  renderUsers();
  renderEmptyMessages();
  renderTypingIndicator();

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
    }
  }

  renderUsers();
}

function handleUserJoined(payload) {
  const user = payload.user;

  if (user && user.userId) {
    state.users.set(user.userId, user);
    renderUsers();
  }
}

function handleUserLeft(payload) {
  if (payload.userId) {
    state.users.delete(payload.userId);
    clearTypingUser(payload.userId);
    renderUsers();
  }
}

function handleMessageReceived(payload) {
  if (!payload.message) {
    return;
  }

  clearTypingUser(payload.message.fromUserId);
  addMessage(payload.message);
}

function handleTypingStarted(payload) {
  if (!payload.userId || !payload.displayName) {
    return;
  }

  if (state.currentUser && payload.userId === state.currentUser.userId) {
    return;
  }

  state.typingUsers.set(payload.userId, payload.displayName);

  const currentTimer = state.typingTimers.get(payload.userId);

  if (currentTimer) {
    window.clearTimeout(currentTimer);
  }

  const timer = window.setTimeout(() => {
    clearTypingUser(payload.userId);
  }, 4000);

  state.typingTimers.set(payload.userId, timer);
  renderTypingIndicator();
}

function handleTypingStopped(payload) {
  if (!payload.userId) {
    return;
  }

  clearTypingUser(payload.userId);
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
  sendEnvelope('typing.start', { roomId: 'global' });
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
    sendEnvelope('typing.stop', { roomId: 'global' });
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
  clearTypingState();

  elements.chatPanel.classList.add('d-none');
  elements.loginPanel.classList.remove('d-none');
  elements.currentDisplayName.textContent = '-';

  if (!keepDisplayName) {
    elements.displayNameInput.value = '';
  }

  setJoinFormEnabled(true);
  renderUsers();
  renderEmptyMessages();
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

  if (state.users.size === 0) {
    const empty = document.createElement('div');
    empty.className = 'empty-state';
    empty.textContent = 'No users online yet.';
    elements.usersList.appendChild(empty);
    return;
  }

  const users = [...state.users.values()].sort((first, second) => {
    return first.displayName.localeCompare(second.displayName);
  });

  for (const user of users) {
    const item = document.createElement('div');
    item.className = 'user-item';

    const avatar = document.createElement('div');
    avatar.className = 'user-avatar';
    avatar.textContent = user.displayName.slice(0, 1).toUpperCase();

    const name = document.createElement('div');
    name.className = 'user-name';
    name.textContent = user.displayName;

    item.appendChild(avatar);
    item.appendChild(name);

    if (state.currentUser && user.userId === state.currentUser.userId) {
      const you = document.createElement('span');
      you.className = 'user-you';
      you.textContent = 'You';
      item.appendChild(you);
    }

    elements.usersList.appendChild(item);
  }
}

function renderTypingIndicator() {
  const names = [...state.typingUsers.values()];

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

function clearTypingUser(userId) {
  if (!userId) {
    return;
  }

  const timer = state.typingTimers.get(userId);

  if (timer) {
    window.clearTimeout(timer);
    state.typingTimers.delete(userId);
  }

  state.typingUsers.delete(userId);
  renderTypingIndicator();
}

function clearTypingState() {
  if (state.typingStopTimer) {
    window.clearTimeout(state.typingStopTimer);
    state.typingStopTimer = null;
  }

  for (const timer of state.typingTimers.values()) {
    window.clearTimeout(timer);
  }

  state.typingUsers.clear();
  state.typingTimers.clear();
  state.isTyping = false;
  state.lastTypingStartSentAt = 0;
  renderTypingIndicator();
}

function renderEmptyMessages() {
  elements.messagesList.replaceChildren();

  const empty = document.createElement('div');
  empty.className = 'empty-state';
  empty.textContent = 'No messages yet. Start the conversation.';

  elements.messagesList.appendChild(empty);
}

function addMessage(message) {
  const empty = elements.messagesList.querySelector('.empty-state');

  if (empty) {
    empty.remove();
  }

  const isOwn = state.currentUser && message.fromUserId === state.currentUser.userId;
  const sender = findDisplayName(message.fromUserId);
  const createdAt = formatTime(message.createdAt);

  const row = document.createElement('div');
  row.className = isOwn ? 'message-row is-own' : 'message-row';

  const meta = document.createElement('div');
  meta.className = 'message-meta';
  meta.textContent = `${sender} • ${createdAt}`;

  const bubble = document.createElement('div');
  bubble.className = 'message-bubble';
  bubble.textContent = message.body || '';

  row.appendChild(meta);
  row.appendChild(bubble);

  elements.messagesList.appendChild(row);
  elements.messagesList.scrollTop = elements.messagesList.scrollHeight;
}

function findDisplayName(userId) {
  const user = state.users.get(userId);

  if (!user) {
    return 'Unknown user';
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
