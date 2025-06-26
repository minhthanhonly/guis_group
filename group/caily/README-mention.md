# Mention Component - Reusable @mention Functionality

This document explains how to use the reusable mention component across the entire website.

## Overview

The mention component provides @mention functionality for input fields, allowing users to mention other users by typing `@` followed by their name. The component is designed to be easily integrated into any page of the website.

## Files

- `assets/js/mention.js` - Main JavaScript component
- `assets/css/mention.css` - CSS styles for the mention functionality
- `application/model/user.php` - Backend API for getting mention users

## Quick Start

### 1. Include the Files

Add these files to your HTML page:

```html
<link rel="stylesheet" href="assets/css/mention.css">
<script src="assets/js/mention.js"></script>
```

### 2. Add the data-mention Attribute

Add the `data-mention` attribute to any input or textarea where you want mention functionality:

```html
<input type="text" class="form-control" data-mention placeholder="コメントを入力... @でメンション">
<textarea class="form-control" data-mention rows="3" placeholder="長いコメントを入力... @でメンション"></textarea>
```

### 3. That's it!

The mention functionality will automatically work. Users can type `@` to see a dropdown of available users.

## Advanced Usage

### Custom Department ID

You can specify a department ID for specific inputs:

```html
<input type="text" class="form-control" data-mention data-department-id="1" placeholder="部署ID 1のユーザーをメンション">
```

### Custom Initialization

For more control, you can create a custom instance:

```javascript
const mentionManager = new MentionManager({
    departmentId: 2, // Default department ID
    inputSelector: 'input[data-mention]', // Custom selector
    apiEndpoint: '/api/index.php?model=user&method=getMentionUsers',
    onMentionSelect: (user, input) => {
        console.log('Mention selected:', user, input);
        // Custom logic here
    }
});
```

### Dynamic Department ID

You can change the department ID dynamically:

```javascript
mentionManager.setDepartmentId(3);
```

## API Integration

The component uses the following API endpoint:

```
GET /api/index.php?model=user&method=getMentionUsers&department_id={department_id}
```

Response format:
```json
[
    {
        "id": 1,
        "userid": "user1",
        "user_name": "田中太郎",
        "role": "manager",
        "authority": "manager"
    }
]
```

## Features

### Automatic Detection
- Detects `@` at the beginning of input or after spaces
- Shows dropdown with filtered users
- Real-time search as you type

### Keyboard Navigation
- **Arrow Down/Up**: Navigate through users
- **Enter**: Select highlighted user
- **Escape**: Close dropdown

### Mouse Interaction
- Click on users in dropdown to select
- Click outside to close dropdown

### Responsive Design
- Works on mobile and desktop
- Adapts to input width
- Scrollable dropdown for many users

### Accessibility
- Keyboard navigation support
- Focus management
- Screen reader friendly

## Rendering Mentions

To render mentions in content, use the static method:

```javascript
const content = "こんにちは @田中太郎 さん";
const rendered = MentionManager.renderMentions(content);
// Result: "こんにちは <span class="mention-highlight">@田中太郎</span> さん"
```

## Styling

The component includes comprehensive CSS with:

- Dropdown styling
- User avatars and initials
- Hover and active states
- Dark mode support
- Responsive design
- Smooth animations

### Custom Styling

You can override styles by targeting these classes:
- `.mention-dropdown` - Main dropdown container
- `.mention-item` - Individual user items
- `.mention-highlight` - Highlighted mentions in content
- `.mention-avatar` - User avatar container
- `.mention-name` - User name
- `.mention-role` - User role

## Integration Examples

### Vue.js Integration

```javascript
// In your Vue component
methods: {
    renderMentions(content) {
        return MentionManager.renderMentions(content);
    }
}
```

```html
<!-- In your template -->
<p v-html="renderMentions(comment.content)"></p>
```

### React Integration

```jsx
const renderMentions = (content) => {
    return { __html: MentionManager.renderMentions(content) };
};

<div dangerouslySetInnerHTML={renderMentions(comment.content)} />
```

### Vanilla JavaScript

```javascript
// Initialize for all inputs with data-mention
document.addEventListener('DOMContentLoaded', () => {
    // Auto-initialized by the component
});

// Render mentions in content
const content = document.querySelector('.content').textContent;
document.querySelector('.content').innerHTML = MentionManager.renderMentions(content);
```

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Troubleshooting

### Dropdown Not Appearing
1. Check if the input has `data-mention` attribute
2. Verify the API endpoint is accessible
3. Check browser console for errors

### Users Not Loading
1. Verify department ID is correct
2. Check API response format
3. Ensure user has proper permissions

### Styling Issues
1. Make sure `mention.css` is loaded
2. Check for CSS conflicts
3. Verify Bootstrap is loaded (for utility classes)

## Demo

See `mention-demo.html` for a complete demonstration of all features.

## License

This component is part of the CAILY project and follows the same license terms. 