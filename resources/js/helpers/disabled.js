/**
 * Give a factory's data its `disabled` getter (or `name`, for a factory that calls it something else).
 *
 * Whether the server marked the control disabled (its own `disabled`, or a disabled form's) is the `data-atom-disabled`
 * marker on its root. It is read from the DOM at event time, never held in `x-data`: a morph re-renders the marker and
 * does not rebuild the factory. It is not reactive, so a binding must not read it. A disabled control writes nothing
 * to its model, on any gesture.
 *
 * `also(data)` adds a second condition (a display-only rating, the picker around a time picker). The getter is
 * defined on the object, not spread into it: a spread would evaluate it, with no `$root` yet.
 */
export default (data, { name = 'disabled', also } = {}) => Object.defineProperty(data, name, {
    get () {
        return this.$root.hasAttribute('data-atom-disabled') || (also ? also(this) : false)
    },
    enumerable: true,
    configurable: true,
})
