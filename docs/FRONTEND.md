# JavaScript Frontend Blueprint

## Frontend technology

Use plain JavaScript or modern ES modules with Laravel Blade for the initial implementation. This keeps the backend and frontend easy to run and debug. Axios may be used for API requests, but native `fetch()` is sufficient.

## Frontend pages

### Landing page

- Product introduction
- Test subjects
- Login button
- Sample test preview

### Authentication page

- Login and registration forms
- Validation messages
- Error response display
- Redirect to dashboard after successful login

### Dashboard page

- Welcome message
- Student name and role
- Total tests available
- Latest score
- Test cards
- Subject cards
- Recent attempts

### Test page

- Test title, subject, duration, and progress
- Bengali question display
- Option radio buttons
- Previous and Next controls
- Save and resume
- Submit confirmation
- Timer warning at 60 seconds and 30 seconds
- 100-question progress indicator
- Negative-marking notice displayed before submission
- Locked tests not shown in the student list
- Test 02 remains locked until test 01 is completed
- Test 03 remains locked until test 02 is completed

### Result page

- Overall score and percentage
- Score ring
- Correct, wrong, and unanswered counts
- Subject-wise result cards
- Topic-wise performance
- Weak topics
- Review question list
- Download report button
- Retry test button

### Admin pages

- Question management
- Test management
- Subject and topic management
- User management
- Dashboard metrics

## JavaScript data flow

```text
Page load
  -> get current user
  -> fetch data from Laravel API
  -> render content
  -> user action
  -> API request
  -> process response
  -> update UI
```

## API client

Example:

```javascript
async function apiRequest(url, options = {}) {
    const response = await fetch(`/api${url}`, {
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            ...options.headers
        },
        ...options
    });

    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.message || 'Request failed');
    }

    return data;
}
```

## State management

Use a small state object or Vue-like reactive state concept if the UI becomes more complex:

```javascript
const state = {
    user: null,
    tests: [],
    currentAttempt: null,
    currentQuestion: null,
    answers: {},
    remainingSeconds: 0,
    timerId: null
};
```

Do not store the final correct answer in the browser unless the student has submitted the test.

## Accessibility requirements

- All controls must be keyboard accessible.
- Use proper ARIA labels for question options and timer.
- Preserve visible focus states.
- Use semantic button and form elements.
- Ensure all color-coded scores have text labels.
- Use sufficient color contrast.

## Responsive design

- Desktop: two-column dashboard and test layout.
- Tablet: single-column content with stacked cards.
- Mobile: compact question options, timer, and navigation buttons.

## Error handling

- Show a loading spinner while requests are pending.
- Display user-friendly error messages.
- Preserve saved answers when an API request fails.
- Avoid closing the test incorrectly after a network error.
- Allow users to retry failed requests.
