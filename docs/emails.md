# Jengo Email Templating System

Jengo provides an opinionated email templating system utilizing Maizzle to generate static markup and CodeIgniter 4 view decorators to dynamically bind data.

---

## 1. Syntax & Variable Resolution

Jengo templates use `%` delimiters for placeholders. It supports resolving nested array keys, object properties, parameterless methods, and getters using dot-notation.

```html
<!-- Array: ['user' => ['name' => 'Ian']] -->
<p>Hello, % user.name %!</p>

<!-- Object Property: $user->email -->
<p>Email: % user.email %</p>

<!-- Object Getter: $user->getAvatar() or Method: $user->avatar() -->
<img src="% user.avatar %" />
```

---

## 2. Filters & Modifiers

Placeholders support piping value results through PHP functions or CodeIgniter helpers. Value is passed as the first parameter to the filter function.

### Piped Filters
Chain multiple functions to apply transformations sequentially:
```html
<!-- Outputs: "ian" -> "Ian" -->
<p>Name: % user.name|strtolower|ucfirst %</p>
```

### Filters with Parameters
Filters support passing additional string, number, boolean, or null arguments:
```html
<!-- Calls: number_to_currency($amount, 'USD', 'en_US') -->
<p>Price: % invoice.amount|number_to_currency('USD', 'en_US') %</p>

<!-- Calls: date($timestamp, 'Y-m-d') -->
<p>Date: % invoice.created_at|date('Y-m-d') %</p>
```

> [!NOTE]
> Jengo automatically attempts to load the CodeIgniter `email` helper when executing the email template decorator, making helper functions immediately available to templates.

---

## 3. Conditionals (`if` / `else` / `endif`)

Jengo supports conditional rendering inside email templates. Conditional blocks evaluate variables, boolean expressions, negations, and comparisons. Conditionals can also be nested.

### Simple Truthiness Check
Checks if a variable resolves to a non-empty/truthy value:
```html
% if user.is_active %
  <p>Status: Active</p>
% endif %
```

### Negation Check
Checks if a variable is falsy/not set:
```html
% if !user.is_verified %
  <p>Please verify your email address.</p>
% endif %
```

### Else Blocks
Fallback markup if the condition evaluates to falsy:
```html
% if user.is_logged_in %
  <p>Welcome back, % user.name %!</p>
% else %
  <p>Please log in to access your account.</p>
% endif %
```

### Comparison Checks
Supports standard comparisons (`==`, `!=`, `>`, `<`, `>=`, `<=`) with strings, numbers, booleans (`true`/`false`), and `null`:
```html
<!-- String Comparison -->
% if user.role == 'admin' %
  <a href="/admin/dashboard">Admin Dashboard</a>
% endif %

<!-- Numeric Comparison -->
% if invoice.total_due > 0 %
  <p>Total Due: % invoice.total_due %</p>
% else %
  <p>Your invoice is fully paid. Thank you!</p>
% endif %

<!-- Boolean Comparison -->
% if user.is_suspended == false %
  <p>Your account is in good standing.</p>
% endif %
```
