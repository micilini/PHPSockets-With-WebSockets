const state = {
  socket: null,
  currentUser: null,
  users: new Map(),
  typingUsers: new Map(),
  typingTimers: new Map(),
  pendingMessages: new Map(),
  messageElements: new Map(),
  messageReadBy: new Map(),
  isTyping: false,
  typingStopTimer: null,
  lastTypingStartSentAt: 0,
  typingHeartbeatMs: 1000,
  typingIdleStopMs: 1400,
};

const EMOJIS = [
  '\u{1F600}', '\u{1F603}', '\u{1F604}', '\u{1F601}', '\u{1F606}', '\u{1F605}', '\u{1F602}', '\u{1F642}',
  '\u{1F60D}', '\u{1F618}', '\u{1F60E}', '\u{1F914}', '\u{1F44D}', '\u{1F44F}', '\u{1F64C}', '\u{1F525}',
  '\u2764\uFE0F', '\u{1F680}', '\u{1F389}', '\u2728', '\u{1F4A1}', '\u2705', '\u{1F4CC}', '\u{1F4E6}',
];

const MAX_ATTACHMENT_BYTES = 524288;
const ALLOWED_ATTACHMENT_MIME_TYPES = [
  'image/png',
  'image/jpeg',
  'image/gif',
  'application/pdf',
  'text/plain',
];

const elements = {
  alertBox: document.getElementById('alertBox'),
  chatPanel: document.getElementById('chatPanel'),
  clearEventsButton: document.getElementById('clearEventsButton'),
  composerActionsButton: document.getElementById('composerActionsButton'),
  composerActionsMenu: document.getElementById('composerActionsMenu'),
  connectionStatus: document.getElementById('connectionStatus'),
  currentDisplayName: document.getElementById('currentDisplayName'),
  displayNameInput: document.getElementById('displayNameInput'),
  emojiPicker: document.getElementById('emojiPicker'),
  eventLog: document.getElementById('eventLog'),
  fileInput: document.getElementById('fileInput'),
  joinButton: document.getElementById('joinButton'),
  joinForm: document.getElementById('joinForm'),
  loginPanel: document.getElementById('loginPanel'),
  messageForm: document.getElementById('messageForm'),
  messageInput: document.getElementById('messageInput'),
  messagesList: document.getElementById('messagesList'),
  onlineCount: document.getElementById('onlineCount'),
  openEmojiButton: document.getElementById('openEmojiButton'),
  openFileButton: document.getElementById('openFileButton'),
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

  const clientMessageId = createClientMessageId();

  clearLocalTypingStateBeforeSend();
  addPendingOwnMessage(text, clientMessageId);
  sendEnvelope('message.global', { text, clientMessageId });
  elements.messageInput.value = '';
  elements.messageInput.focus();
});

elements.messageInput.addEventListener('input', () => {
  handleTypingInput();
});

elements.messageInput.addEventListener('blur', () => {
  stopTyping();
});

elements.clearEventsButton.addEventListener('click', () => {
  elements.eventLog.replaceChildren();
});

elements.composerActionsButton.addEventListener('click', () => {
  toggleComposerActionsMenu();
});

elements.openEmojiButton.addEventListener('click', () => {
  closeComposerActionsMenu();
  toggleEmojiPicker();
});

elements.openFileButton.addEventListener('click', () => {
  closeComposerActionsMenu();
  elements.fileInput.click();
});

elements.fileInput.addEventListener('change', () => {
  handleSelectedFile();
});

document.addEventListener('click', (event) => {
  const target = event.target;

  if (!(target instanceof Element)) {
    return;
  }

  if (!target.closest('.composer-actions')) {
    closeComposerActionsMenu();
    closeEmojiPicker();
  }
});

window.addEventListener('beforeunload', () => {
  stopTyping();

  if (state.socket && state.socket.readyState === WebSocket.OPEN) {
    state.socket.close();
  }
});

renderEmojiPicker();
renderEmptyMessages();
renderTypingIndicator();
setStatus('Disconnected', 'offline');
logBrowserEvent('browser.ready');

function connect(serverUrl, displayName) {
  disconnect();
  clearAlert();
  setStatus('Connecting', 'connecting');
  setJoinFormEnabled(false);
  logBrowserEvent('socket.connecting');

  try {
    state.socket = new WebSocket(serverUrl);
  } catch (error) {
    setJoinFormEnabled(true);
    setStatus('Disconnected', 'offline');
    showAlert('Invalid WebSocket server URL.', 'danger');
    logBrowserEvent('socket.error');
    return;
  }

  state.socket.addEventListener('open', () => {
    logBrowserEvent('socket.open');
    sendEnvelope('auth.join', { displayName });
  });

  state.socket.addEventListener('message', (event) => {
    handleServerMessage(event.data);
  });

  state.socket.addEventListener('close', () => {
    const hadCurrentUser = Boolean(state.currentUser);

    logBrowserEvent('socket.close');
    setStatus('Disconnected', 'offline');

    if (hadCurrentUser) {
      showAlert('Connection closed. Start the server again and re-enter the chat.', 'warning');
      resetToLogin(false);
      return;
    }

    resetToLogin(true);
  });

  state.socket.addEventListener('error', () => {
    logBrowserEvent('socket.error');
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

  logBrowserEvent(envelope.type || 'server.unknown');

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

    case 'attachment.accepted':
      handleAttachmentAccepted(envelope.payload);
      break;

    case 'attachment.rejected':
      handleAttachmentRejected(envelope.payload);
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

  const message = payload.message;
  const isOwn = state.currentUser && message.fromUserId === state.currentUser.userId;
  const clientMessageId = message.metadata && message.metadata.clientMessageId;

  clearTypingUser(message.fromUserId);

  if (isOwn && clientMessageId && state.pendingMessages.has(clientMessageId)) {
    state.pendingMessages.delete(clientMessageId);
    updatePendingMessageAsReceived(clientMessageId, message);
    return;
  }

  addMessage(message);

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

function handleAttachmentAccepted(payload) {
  void payload;
}

function handleAttachmentRejected(payload) {
  const message = payload && typeof payload.message === 'string'
    ? payload.message
    : 'Attachment was rejected.';

  showAlert(message, 'warning');
}

function sendEnvelope(type, payload) {
  if (!state.socket || state.socket.readyState !== WebSocket.OPEN) {
    showAlert('WebSocket connection is not open.', 'danger');
    return;
  }

  logBrowserEvent(clientEventName(type));
  state.socket.send(JSON.stringify({ type, payload }));
}

function clientEventName(type) {
  const clientEvents = {
    'auth.join': 'client.auth.join',
    'attachment.prepare': 'client.attachment.prepare',
    'message.global': 'client.message.global',
    'message.file': 'client.message.file',
    'message.read': 'client.message.read',
    'typing.start': 'client.typing.start',
    'typing.stop': 'client.typing.stop',
  };

  return clientEvents[type] || `client.${type}`;
}

function logBrowserEvent(name) {
  const item = document.createElement('div');
  item.className = 'event-item';

  const title = document.createElement('div');
  title.className = 'event-title';
  title.textContent = name;

  const time = document.createElement('div');
  time.className = 'event-time';
  time.textContent = formatTime(new Date().toISOString());

  item.appendChild(title);
  item.appendChild(time);
  elements.eventLog.prepend(item);

  while (elements.eventLog.children.length > 80) {
    elements.eventLog.lastElementChild.remove();
  }
}

function createClientMessageId() {
  return `client_${Date.now()}_${Math.random().toString(16).slice(2)}`;
}

function addPendingOwnMessage(text, clientMessageId) {
  const message = {
    id: clientMessageId,
    roomId: 'global',
    fromUserId: state.currentUser ? state.currentUser.userId : null,
    kind: 'text',
    body: text,
    metadata: { clientMessageId },
    createdAt: new Date().toISOString(),
    status: 'sent',
  };

  state.pendingMessages.set(clientMessageId, message);
  addMessage(message);
}

function addPendingOwnFileMessage(file, clientMessageId) {
  const message = {
    id: clientMessageId,
    roomId: 'global',
    fromUserId: state.currentUser ? state.currentUser.userId : null,
    kind: 'file',
    body: {
      fileName: file.name,
      mimeType: file.type,
      sizeBytes: file.size,
      previewDataUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
    },
    metadata: { clientMessageId },
    createdAt: new Date().toISOString(),
    status: 'sent',
  };

  state.pendingMessages.set(clientMessageId, message);
  addMessage(message);
}

function updatePendingMessageAsReceived(clientMessageId, message) {
  const row = state.messageElements.get(clientMessageId);

  if (!row) {
    addMessage(message);
    updateMessageStatus(message.id, 'received');
    return;
  }

  state.messageElements.delete(clientMessageId);
  state.messageElements.set(message.id, row);
  row.dataset.messageId = message.id;

  const status = row.querySelector('.message-status');
  updateStatusElement(status, 'received');
}

function updateMessageStatus(messageId, statusName) {
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
  state.pendingMessages.clear();
  state.messageElements.clear();
  state.messageReadBy.clear();
  clearTypingState();
  closeComposerActionsMenu();
  closeEmojiPicker();

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

function toggleComposerActionsMenu() {
  const isHidden = elements.composerActionsMenu.classList.contains('d-none');

  elements.composerActionsMenu.classList.toggle('d-none', !isHidden);
  elements.composerActionsMenu.setAttribute('aria-hidden', isHidden ? 'false' : 'true');

  closeEmojiPicker();
}

function closeComposerActionsMenu() {
  elements.composerActionsMenu.classList.add('d-none');
  elements.composerActionsMenu.setAttribute('aria-hidden', 'true');
}

function toggleEmojiPicker() {
  const isHidden = elements.emojiPicker.classList.contains('d-none');

  elements.emojiPicker.classList.toggle('d-none', !isHidden);
  elements.emojiPicker.setAttribute('aria-hidden', isHidden ? 'false' : 'true');
}

function closeEmojiPicker() {
  elements.emojiPicker.classList.add('d-none');
  elements.emojiPicker.setAttribute('aria-hidden', 'true');
}

function renderEmojiPicker() {
  elements.emojiPicker.replaceChildren();

  for (const emoji of EMOJIS) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'emoji-button';
    button.textContent = emoji;
    button.title = emoji;

    button.addEventListener('click', () => {
      insertAtCursor(elements.messageInput, emoji);
      closeEmojiPicker();
    });

    elements.emojiPicker.appendChild(button);
  }
}

function insertAtCursor(input, value) {
  const start = input.selectionStart || 0;
  const end = input.selectionEnd || 0;
  const before = input.value.slice(0, start);
  const after = input.value.slice(end);

  input.value = `${before}${value}${after}`;
  input.selectionStart = start + value.length;
  input.selectionEnd = start + value.length;
  input.focus();

  handleTypingInput();
}

async function handleSelectedFile() {
  const file = elements.fileInput.files && elements.fileInput.files[0]
    ? elements.fileInput.files[0]
    : null;

  elements.fileInput.value = '';

  if (!file) {
    return;
  }

  if (file.size > MAX_ATTACHMENT_BYTES) {
    showAlert(`File is too large. Maximum size is ${formatFileSize(MAX_ATTACHMENT_BYTES)}.`, 'warning');
    return;
  }

  if (!isAllowedAttachment(file)) {
    showAlert('This file type is not allowed.', 'warning');
    return;
  }

  try {
    sendEnvelope('attachment.prepare', {
      fileName: file.name,
      mimeType: file.type,
      sizeBytes: file.size,
    });

    const contentBase64 = await readFileAsBase64(file);
    const clientMessageId = createClientMessageId();

    addPendingOwnFileMessage(file, clientMessageId);

    sendEnvelope('message.file', {
      scope: 'global',
      clientMessageId,
      attachment: {
        fileName: file.name,
        mimeType: file.type,
        sizeBytes: file.size,
        contentBase64,
      },
    });
  } catch (error) {
    showAlert(error instanceof Error ? error.message : 'Failed to send file.', 'danger');
  }
}

function isAllowedAttachment(file) {
  return ALLOWED_ATTACHMENT_MIME_TYPES.includes(file.type);
}

function formatFileSize(sizeBytes) {
  if (sizeBytes < 1024) {
    return `${sizeBytes} B`;
  }

  if (sizeBytes < 1024 * 1024) {
    return `${(sizeBytes / 1024).toFixed(1)} KB`;
  }

  return `${(sizeBytes / 1024 / 1024).toFixed(1)} MB`;
}

function readFileAsBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();

    reader.addEventListener('load', () => {
      const result = reader.result;

      if (typeof result !== 'string') {
        reject(new Error('Failed to read file.'));
        return;
      }

      const parts = result.split(',');
      resolve(parts.length > 1 ? parts[1] : result);
    });

    reader.addEventListener('error', () => {
      reject(new Error('Failed to read file.'));
    });

    reader.readAsDataURL(file);
  });
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
  state.pendingMessages.clear();
  state.messageElements.clear();
  state.messageReadBy.clear();
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
  row.dataset.messageId = message.id;

  const footer = document.createElement('div');
  footer.className = 'message-footer';

  const meta = document.createElement('div');
  meta.className = 'message-meta';
  meta.textContent = `${sender} - ${createdAt}`;

  const status = document.createElement('span');
  status.className = 'message-status';
  updateStatusElement(status, isOwn ? message.status || 'received' : 'received');

  const bubble = createMessageBodyElement(message);

  footer.appendChild(meta);
  footer.appendChild(status);
  row.appendChild(footer);
  row.appendChild(bubble);

  state.messageElements.set(message.id, row);
  elements.messagesList.appendChild(row);
  elements.messagesList.scrollTop = elements.messagesList.scrollHeight;
}

function createMessageBodyElement(message) {
  if (message.kind === 'file') {
    return createFileMessageElement(message.body || {});
  }

  const bubble = document.createElement('div');
  bubble.className = 'message-bubble';
  bubble.textContent = message.body || '';

  return bubble;
}

function createFileMessageElement(body) {
  const card = document.createElement('div');
  card.className = 'message-bubble file-message';

  const fileName = typeof body.fileName === 'string' ? body.fileName : 'Attachment';
  const mimeType = typeof body.mimeType === 'string' ? body.mimeType : 'application/octet-stream';
  const sizeBytes = typeof body.sizeBytes === 'number' ? body.sizeBytes : 0;
  const previewDataUrl = typeof body.previewDataUrl === 'string' ? body.previewDataUrl : null;

  if (previewDataUrl && mimeType.startsWith('image/')) {
    const image = document.createElement('img');
    image.className = 'file-message-preview';
    image.src = previewDataUrl;
    image.alt = fileName;
    card.appendChild(image);
  }

  const info = document.createElement('div');
  info.className = 'file-message-info';

  const icon = document.createElement('span');
  icon.className = 'file-message-icon';
  icon.textContent = mimeType.startsWith('image/') ? 'IMG' : mimeType === 'application/pdf' ? 'PDF' : 'TXT';

  const text = document.createElement('div');

  const name = document.createElement('strong');
  name.textContent = fileName;

  const meta = document.createElement('small');
  meta.textContent = `${mimeType} - ${formatFileSize(sizeBytes)}`;

  text.appendChild(name);
  text.appendChild(meta);

  info.appendChild(icon);
  info.appendChild(text);
  card.appendChild(info);

  return card;
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
