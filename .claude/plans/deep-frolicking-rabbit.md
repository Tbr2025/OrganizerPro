# Plan: Sidebar Layout Redesign (Paces-style)

## Context
User wants the admin sidebar restyled to match a clean, dark, spacious design (Paces template screenshot). The current sidebar already has the right structure (Alpine.js toggle, Iconify icons, collapsible groups, role-based menus). Only visual/CSS changes are needed.

## Key Differences (Current → Target)
1. **Icon wrappers**: Remove the `bg-gray-50` rounded boxes around icons → bare icons inline
2. **Submenu style**: Remove left border + bullet dots → simple indented text
3. **Spacing**: Tighter items → more spacious padding
4. **Active state**: Cyan bar + bg highlight → subtle background highlight, no left bar
5. **Group headings**: Already uppercase — just adjust color/spacing to match
6. **Overall feel**: Less visual noise, cleaner lines

## Files to Modify

### 1. `resources/css/sidebar.css`
- Remove icon-wrapper background styles
- Simplify submenu: no left border, no dot indicators, just indented text
- Increase item padding (py-2.5 → py-3)
- Simplify active state: subtle bg, bold text, no left bar pill
- Clean hover states

### 2. `resources/views/backend/layouts/partials/menu-item.blade.php`
- Remove `menu-item-icon-wrapper` div with bg classes — render icon directly
- Simplify submenu `<ul>`: remove `border-l-2`, reduce left margin
- Keep chevron arrow behavior (already matches screenshot)

### 3. `resources/views/backend/layouts/partials/sidebar-menu.blade.php`
- Adjust group heading spacing if needed (minor)

### 4. `resources/views/backend/layouts/partials/sidebar-logo.blade.php`
- No structural changes needed — already has logo + minified icon support

## Verification
- Check sidebar in both light and dark mode
- Verify collapsible submenus still work
- Verify minified state still works on desktop
- Verify mobile sidebar toggle still works
- Check active state highlighting on current page
- Test with Team Manager (simplified menu) and Admin (full menu)
