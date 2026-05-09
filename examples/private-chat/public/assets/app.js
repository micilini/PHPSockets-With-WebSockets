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
  unreadCounts: new Map(),
  selectedAttachment: null,
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

const EMOJIS = [
  '\u{1F600}', '\u{1F603}', '\u{1F604}', '\u{1F601}', '\u{1F606}', '\u{1F605}', '\u{1F602}', '\u{1F642}',
  '\u{1F60D}', '\u{1F618}', '\u{1F60E}', '\u{1F914}', '\u{1F44D}', '\u{1F44F}', '\u{1F64C}', '\u{1F525}',
  '\u2764\uFE0F', '\u{1F680}', '\u{1F389}', '\u2728', '\u{1F4A1}', '\u2705', '\u{1F4CC}', '\u{1F4E6}',
];

const MAX_ATTACHMENT_BYTES = 2 * 1024 * 1024;
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
  closePrivateRoomModalButton: document.getElementById('closePrivateRoomModalButton'),
  composerActionsButton: document.getElementById('composerActionsButton'),
  composerActionsMenu: document.getElementById('composerActionsMenu'),
  connectionStatus: document.getElementById('connectionStatus'),
  conversationEyebrow: document.getElementById('conversationEyebrow'),
  conversationTitle: document.getElementById('conversationTitle'),
  createPrivateRoomButton: document.getElementById('createPrivateRoomButton'),
  currentDisplayName: document.getElementById('currentDisplayName'),
  displayNameInput: document.getElementById('displayNameInput'),
  emojiPicker: document.getElementById('emojiPicker'),
  fileInput: document.getElementById('fileInput'),
  globalRoomButton: document.getElementById('globalRoomButton'),
  groupRoomsList: document.getElementById('groupRoomsList'),
  joinButton: document.getElementById('joinButton'),
  joinForm: document.getElementById('joinForm'),
  loginPanel: document.getElementById('loginPanel'),
  messageForm: document.getElementById('messageForm'),
  messageInput: document.getElementById('messageInput'),
  messagesList: document.getElementById('messagesList'),
  newPrivateRoomButton: document.getElementById('newPrivateRoomButton'),
  onlineCount: document.getElementById('onlineCount'),
  openEmojiButton: document.getElementById('openEmojiButton'),
  openFileButton: document.getElementById('openFileButton'),
  privateRoomForm: document.getElementById('privateRoomForm'),
  privateRoomModal: document.getElementById('privateRoomModal'),
  privateRoomNameInput: document.getElementById('privateRoomNameInput'),
  privateRoomUsersList: document.getElementById('privateRoomUsersList'),
  serverUrlInput: document.getElementById('serverUrlInput'),
  selectedAttachmentPreview: document.getElementById('selectedAttachmentPreview'),
  typingIndicator: document.getElementById('typingIndicator'),
  usersList: document.getElementById('usersList'),
};

let alertDismissTimer = null;

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

elements.newPrivateRoomButton.addEventListener('click', () => {
  openPrivateRoomModal();
});

elements.closePrivateRoomModalButton.addEventListener('click', () => {
  closePrivateRoomModal();
});

elements.privateRoomModal.addEventListener('click', (event) => {
  if (event.target && event.target.getAttribute('data-close-room-modal') === 'true') {
    closePrivateRoomModal();
  }
});

elements.privateRoomForm.addEventListener('submit', (event) => {
  event.preventDefault();
  createPrivateRoomFromForm();
});

elements.messageForm.addEventListener('submit', async (event) => {
  event.preventDefault();

  const text = elements.messageInput.value.trim();
  const selectedAttachment = state.selectedAttachment;
  const conversation = state.conversations.get(state.activeConversationId);

  if (!text && !selectedAttachment) {
    stopTyping();
    return;
  }

  if (!conversation) {
    showAlert('Choose a conversation before sending a message.', 'warning');
    return;
  }

  if (selectedAttachment) {
    await sendSelectedAttachment(text);
    return;
  }

  const clientMessageId = createClientMessageId();

  clearLocalTypingStateBeforeSend();
  addPendingOwnMessage(text, clientMessageId, conversation.id);

  if (conversation.type === 'global') {
    sendEnvelope('message.global', { text, clientMessageId });
  } else if (conversation.type === 'direct') {
    sendEnvelope('message.direct', {
      toUserId: conversation.targetUserId,
      text,
      clientMessageId,
    });
  } else {
    sendEnvelope('room.message', {
      roomId: conversation.roomId,
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
renderConversationHeader();
renderGroupRooms();
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

    case 'attachment.accepted':
      handleAttachmentAccepted(envelope.payload);
      break;

    case 'attachment.rejected':
      handleAttachmentRejected(envelope.payload);
      break;

    case 'room.created':
      handleRoomCreated(envelope.payload);
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
  renderGroupRooms();

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
  renderGroupRooms();
}

function handleUserJoined(payload) {
  const user = payload.user;

  if (user && user.userId) {
    state.users.set(user.userId, user);
    ensureDirectConversationFromUserId(user.userId);
    renderUsers();
    renderGroupRooms();
  }
}

function handleUserLeft(payload) {
  if (payload.userId) {
    state.users.delete(payload.userId);
    clearTypingUserInAllConversations(payload.userId);
    renderUsers();
    renderGroupRooms();
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

  if (!isOwn && conversationId !== state.activeConversationId) {
    incrementUnread(conversationId);
  }

  if (!isOwn) {
    sendEnvelope('message.read', {
      messageId: message.id,
      roomId: message.roomId || 'global',
    });
  }
}

function handleRoomCreated(payload) {
  if (!payload.room || !payload.room.id) {
    return;
  }

  const room = payload.room;
  const conversationId = groupConversationId(room.id);
  const title = room.name || groupTitleFromMembers(room.memberUserIds || []);

  state.conversations.set(conversationId, {
    id: conversationId,
    type: 'private_group',
    title,
    subtitle: 'Private group',
    targetUserId: null,
    roomId: room.id,
    memberUserIds: Array.isArray(room.memberUserIds) ? room.memberUserIds : [],
    createdBy: room.createdBy || null,
  });

  renderGroupRooms();

  if (state.currentUser && room.createdBy === state.currentUser.userId) {
    setActiveConversation(conversationId);
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

  state.socket.send(JSON.stringify({ type, payload }));
}

function openPrivateRoomModal() {
  renderPrivateRoomUsersList();
  elements.privateRoomModal.classList.remove('d-none');
  elements.privateRoomModal.setAttribute('aria-hidden', 'false');
  elements.privateRoomNameInput.focus();
}

function closePrivateRoomModal() {
  elements.privateRoomModal.classList.add('d-none');
  elements.privateRoomModal.setAttribute('aria-hidden', 'true');
  elements.privateRoomNameInput.value = '';
  elements.privateRoomUsersList.replaceChildren();
  elements.createPrivateRoomButton.disabled = true;
}

function selectedPrivateRoomUserIds() {
  return [...elements.privateRoomUsersList.querySelectorAll('input[type="checkbox"]:checked')]
    .map((input) => input.value)
    .filter(Boolean);
}

function renderPrivateRoomUsersList() {
  elements.privateRoomUsersList.replaceChildren();

  const users = [...state.users.values()]
    .filter((user) => !state.currentUser || user.userId !== state.currentUser.userId)
    .sort((first, second) => first.displayName.localeCompare(second.displayName));

  if (users.length === 0) {
    const empty = document.createElement('div');
    empty.className = 'empty-state';
    empty.textContent = 'No other users online.';
    elements.privateRoomUsersList.appendChild(empty);
    elements.createPrivateRoomButton.disabled = true;
    return;
  }

  for (const user of users) {
    const label = document.createElement('label');
    label.className = 'room-user-option';

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.value = user.userId;

    checkbox.addEventListener('change', () => {
      elements.createPrivateRoomButton.disabled = selectedPrivateRoomUserIds().length === 0;
    });

    const name = document.createElement('span');
    name.className = 'room-user-option-name';
    name.textContent = user.displayName;

    label.appendChild(checkbox);
    label.appendChild(name);

    elements.privateRoomUsersList.appendChild(label);
  }

  elements.createPrivateRoomButton.disabled = true;
}

function createPrivateRoomFromForm() {
  const participantUserIds = selectedPrivateRoomUserIds();

  if (participantUserIds.length === 0) {
    showAlert('Select at least one participant.', 'warning');
    return;
  }

  const name = elements.privateRoomNameInput.value.trim();

  sendEnvelope('room.create', {
    type: 'private_group',
    name: name || null,
    participantUserIds,
  });

  closePrivateRoomModal();
}

function directConversationId(userId) {
  return `direct:${userId}`;
}

function groupConversationId(roomId) {
  return `room:${roomId}`;
}

function unreadCountForConversation(conversationId) {
  return state.unreadCounts.get(conversationId) || 0;
}

function formatUnreadCount(count) {
  if (count <= 0) {
    return '';
  }

  return count > 99 ? '99+' : String(count);
}

function incrementUnread(conversationId) {
  if (conversationId === state.activeConversationId) {
    return;
  }

  const current = unreadCountForConversation(conversationId);
  state.unreadCounts.set(conversationId, current + 1);
  renderConversationBadges();
}

function clearUnread(conversationId) {
  state.unreadCounts.delete(conversationId);
  renderConversationBadges();
}

function renderConversationBadges() {
  const globalBadge = elements.globalRoomButton.querySelector('.unread-badge');
  updateBadgeElement(globalBadge, unreadCountForConversation('global'));

  for (const item of elements.usersList.querySelectorAll('[data-conversation-id]')) {
    const conversationId = item.getAttribute('data-conversation-id');
    const badge = item.querySelector('.unread-badge');

    if (conversationId && badge) {
      updateBadgeElement(badge, unreadCountForConversation(conversationId));
    }
  }

  if (elements.groupRoomsList) {
    for (const item of elements.groupRoomsList.querySelectorAll('[data-conversation-id]')) {
      const conversationId = item.getAttribute('data-conversation-id');
      const badge = item.querySelector('.unread-badge');

      if (conversationId && badge) {
        updateBadgeElement(badge, unreadCountForConversation(conversationId));
      }
    }
  }
}

function updateBadgeElement(element, count) {
  if (!element) {
    return;
  }

  const label = formatUnreadCount(count);

  element.textContent = label;
  element.classList.toggle('d-none', label === '');
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

function groupTitleFromMembers(memberUserIds) {
  const names = memberUserIds
    .filter((userId) => !state.currentUser || userId !== state.currentUser.userId)
    .map((userId) => {
      const user = state.users.get(userId);
      return user ? user.displayName : 'Unknown user';
    })
    .filter(Boolean);

  if (names.length === 0) {
    return 'Private room';
  }

  if (names.length <= 3) {
    return names.join(', ');
  }

  return `${names.slice(0, 3).join(', ')} +${names.length - 3}`;
}

function renderGroupRooms() {
  if (!elements.groupRoomsList) {
    return;
  }

  elements.groupRoomsList.replaceChildren();

  const rooms = [...state.conversations.values()]
    .filter((conversation) => conversation.type === 'private_group')
    .sort((first, second) => first.title.localeCompare(second.title));

  if (rooms.length === 0) {
    const empty = document.createElement('div');
    empty.className = 'empty-state compact-empty';
    empty.textContent = 'No private rooms yet.';
    elements.groupRoomsList.appendChild(empty);
    return;
  }

  for (const conversation of rooms) {
    const item = document.createElement('button');
    item.type = 'button';
    item.className = conversation.id === state.activeConversationId
      ? 'conversation-item conversation-item-active'
      : 'conversation-item';
    item.dataset.conversationId = conversation.id;

    const avatar = document.createElement('span');
    avatar.className = 'conversation-avatar';
    avatar.textContent = 'G';

    const info = document.createElement('span');
    info.className = 'conversation-info';

    const title = document.createElement('strong');
    title.textContent = conversation.title;

    const subtitle = document.createElement('small');
    subtitle.textContent = `${conversation.memberUserIds ? conversation.memberUserIds.length : 0} members`;

    const badge = document.createElement('span');
    badge.className = 'unread-badge d-none';

    info.appendChild(title);
    info.appendChild(subtitle);

    item.appendChild(avatar);
    item.appendChild(info);
    item.appendChild(badge);

    item.addEventListener('click', () => {
      setActiveConversation(conversation.id);
    });

    elements.groupRoomsList.appendChild(item);
  }

  renderConversationBadges();
}

function setActiveConversation(conversationId) {
  if (!state.conversations.has(conversationId)) {
    return;
  }

  stopTyping();

  state.activeConversationId = conversationId;
  clearUnread(conversationId);
  renderConversationHeader();
  renderUsers();
  renderGroupRooms();
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
  elements.conversationEyebrow.textContent = conversation.type === 'global'
    ? 'Global room'
    : conversation.type === 'direct'
      ? 'Private direct'
      : 'Private group';
  elements.globalRoomButton.classList.toggle('conversation-item-active', conversation.id === 'global');
}

function conversationIdForMessage(message) {
  if (message.roomId === 'global') {
    return 'global';
  }

  const roomConversationId = groupConversationId(message.roomId);

  if (state.conversations.has(roomConversationId)) {
    return roomConversationId;
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

  if (payload.scope === 'room' && payload.roomId) {
    return groupConversationId(payload.roomId);
  }

  return 'global';
}

function typingPayloadForActiveConversation() {
  const conversation = state.conversations.get(state.activeConversationId);

  if (!conversation || conversation.type === 'global') {
    return { roomId: 'global' };
  }

  if (conversation.type === 'direct') {
    return {
      scope: 'direct',
      toUserId: conversation.targetUserId,
      roomId: conversation.roomId || null,
    };
  }

  return {
    scope: 'room',
    roomId: conversation.roomId,
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
  const conversation = state.conversations.get(conversationId);
  const message = {
    id: clientMessageId,
    roomId: conversation && conversation.roomId ? conversation.roomId : 'global',
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

function addPendingOwnFileMessage(file, clientMessageId, conversationId, caption = '') {
  const conversation = state.conversations.get(conversationId);
  const previewUrl = file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
  const downloadUrl = URL.createObjectURL(file);
  const message = {
    id: clientMessageId,
    roomId: conversation && conversation.roomId ? conversation.roomId : 'global',
    fromUserId: state.currentUser ? state.currentUser.userId : null,
    kind: 'file',
    body: {
      fileName: file.name,
      mimeType: file.type,
      sizeBytes: file.size,
      previewDataUrl: previewUrl,
      downloadDataUrl: downloadUrl,
      caption,
    },
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
  state.unreadCounts.clear();
  clearTypingState();
  closeComposerActionsMenu();
  closeEmojiPicker();
  clearSelectedAttachment();

  elements.chatPanel.classList.add('d-none');
  elements.loginPanel.classList.remove('d-none');
  elements.currentDisplayName.textContent = '-';

  if (!keepDisplayName) {
    elements.displayNameInput.value = '';
  }

  setJoinFormEnabled(true);
  renderConversationHeader();
  renderUsers();
  renderGroupRooms();
  renderMessages();
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

function handleSelectedFile() {
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

  setSelectedAttachment(file);
}

function setSelectedAttachment(file) {
  clearSelectedAttachment();

  const previewUrl = file.type.startsWith('image/')
    ? URL.createObjectURL(file)
    : null;

  state.selectedAttachment = { file, previewUrl };

  renderSelectedAttachmentPreview();
}

function clearSelectedAttachment() {
  if (state.selectedAttachment && state.selectedAttachment.previewUrl) {
    URL.revokeObjectURL(state.selectedAttachment.previewUrl);
  }

  state.selectedAttachment = null;
  renderSelectedAttachmentPreview();
}

function renderSelectedAttachmentPreview() {
  elements.selectedAttachmentPreview.replaceChildren();

  if (!state.selectedAttachment) {
    elements.selectedAttachmentPreview.classList.add('d-none');
    return;
  }

  const { file, previewUrl } = state.selectedAttachment;

  elements.selectedAttachmentPreview.classList.remove('d-none');

  const info = document.createElement('div');
  info.className = 'selected-attachment-info';

  const thumb = document.createElement('span');
  thumb.className = 'selected-attachment-thumb';

  if (previewUrl) {
    const image = document.createElement('img');
    image.src = previewUrl;
    image.alt = file.name;
    thumb.appendChild(image);
  } else {
    thumb.textContent = file.type === 'application/pdf' ? 'PDF' : 'TXT';
  }

  const text = document.createElement('div');
  text.className = 'selected-attachment-text';

  const name = document.createElement('strong');
  name.textContent = file.name;

  const meta = document.createElement('small');
  meta.textContent = `${file.type || 'unknown'} - ${formatFileSize(file.size)}`;

  text.appendChild(name);
  text.appendChild(meta);
  info.appendChild(thumb);
  info.appendChild(text);

  const removeButton = document.createElement('button');
  removeButton.type = 'button';
  removeButton.className = 'remove-attachment-button';
  removeButton.textContent = 'Remove';

  removeButton.addEventListener('click', () => {
    clearSelectedAttachment();
    elements.messageInput.focus();
  });

  elements.selectedAttachmentPreview.appendChild(info);
  elements.selectedAttachmentPreview.appendChild(removeButton);
}

async function sendSelectedAttachment(caption) {
  if (!state.selectedAttachment) {
    return;
  }

  const { file } = state.selectedAttachment;
  const conversationId = state.activeConversationId;

  if (file.size > MAX_ATTACHMENT_BYTES) {
    showAlert(`File is too large. Maximum size is ${formatFileSize(MAX_ATTACHMENT_BYTES)}.`, 'warning');
    return;
  }

  if (!isAllowedAttachment(file)) {
    showAlert('This file type is not allowed.', 'warning');
    return;
  }

  try {
    clearLocalTypingStateBeforeSend();

    const contentBase64 = await readFileAsBase64(file);
    const clientMessageId = createClientMessageId();

    addPendingOwnFileMessage(file, clientMessageId, conversationId, caption);
    sendEnvelope('message.file', fileMessagePayloadForActiveConversation(file, clientMessageId, contentBase64, caption));

    elements.messageInput.value = '';
    clearSelectedAttachment();
    elements.messageInput.focus();
  } catch (error) {
    showAlert(error instanceof Error ? error.message : 'Failed to send file.', 'danger');
  }
}

function fileMessagePayloadForActiveConversation(file, clientMessageId, contentBase64, caption) {
  const conversation = state.conversations.get(state.activeConversationId);
  const attachment = {
    fileName: file.name,
    mimeType: file.type,
    sizeBytes: file.size,
    contentBase64,
  };

  if (!conversation || conversation.type === 'global') {
    return { scope: 'global', clientMessageId, caption, attachment };
  }

  if (conversation.type === 'direct') {
    return {
      scope: 'direct',
      toUserId: conversation.targetUserId,
      clientMessageId,
      caption,
      attachment,
    };
  }

  return {
    scope: 'room',
    roomId: conversation.roomId,
    clientMessageId,
    caption,
    attachment,
  };
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

      const commaIndex = result.indexOf(',');

      if (commaIndex === -1) {
        reject(new Error('Failed to parse file content.'));
        return;
      }

      resolve(result.slice(commaIndex + 1));
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

function showAlert(message, type = 'danger', autoDismissMs = 5000) {
  if (alertDismissTimer) {
    window.clearTimeout(alertDismissTimer);
    alertDismissTimer = null;
  }

  elements.alertBox.className = `alert app-alert alert-${type}`;
  elements.alertBox.textContent = message;
  elements.alertBox.classList.remove('d-none');

  if (autoDismissMs > 0) {
    alertDismissTimer = window.setTimeout(() => {
      hideAlert();
    }, autoDismissMs);
  }
}

function clearAlert() {
  hideAlert();
}

function hideAlert() {
  if (alertDismissTimer) {
    window.clearTimeout(alertDismissTimer);
    alertDismissTimer = null;
  }

  elements.alertBox.classList.add('d-none');
  elements.alertBox.textContent = '';
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
    item.dataset.conversationId = directConversationId(user.userId);
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

    const badge = document.createElement('span');
    badge.className = 'unread-badge d-none';

    item.appendChild(avatar);
    item.appendChild(name);
    item.appendChild(badge);

    elements.usersList.appendChild(item);
  }

  renderConversationBadges();
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

  const bubble = createMessageBodyElement(message);

  footer.appendChild(meta);
  footer.appendChild(status);
  row.appendChild(footer);
  row.appendChild(bubble);

  state.messageElements.set(message.id, row);
  elements.messagesList.appendChild(row);
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
  const downloadUrl = typeof body.downloadDataUrl === 'string' ? body.downloadDataUrl : previewDataUrl;
  const caption = typeof body.caption === 'string' ? body.caption.trim() : '';

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

  if (caption) {
    const captionElement = document.createElement('p');
    captionElement.className = 'file-message-caption';
    captionElement.textContent = caption;
    card.appendChild(captionElement);
  }

  if (downloadUrl) {
    const downloadLink = document.createElement('a');
    downloadLink.className = 'file-download-button';
    downloadLink.href = downloadUrl;
    downloadLink.download = fileName;
    downloadLink.textContent = 'Download';
    downloadLink.rel = 'noopener';
    card.appendChild(downloadLink);
  }

  return card;
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
