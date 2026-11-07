---
outline: deep
---

# Tooltips

The usually will use [PrimeVue's](https://primevue.org/tooltip/) tooltip component. There are 3 types of tooltips to use:

1. <strong>Imediate Tooltips:</strong> used for important actions that always need context, no matter the experience of the user;
2. <strong>Delayed Tooltips 1:</strong> used for important actions that don't need context for experienced users. Used with `showDelay: 500`
3. <strong>Delayed Tooltips 2:</strong> used for actions that don't need context for experienced users but should have a tooltip for some reason. Used with `showDelay: 1000`

## Usage

### Imediate Tooltip

```Vue
<InputText v-tooltip="'Enter your username'" />
<InputText v-tooltip.top="'Enter your username'" />
<InputText v-tooltip.bottom="'Enter your username'" />
<InputText v-tooltip.left="'Enter your username'" />
```

### Delayed Tooltips 1

```Vue
<Button v-tooltip="{ value: 'Confirm to proceed', showDelay: 500 }"  />
```

### Delayed Tooltips 2

```Vue
<Button v-tooltip="{ value: 'Confirm to proceed', showDelay: 1000 }"  />
```
