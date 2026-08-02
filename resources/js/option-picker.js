/**
 * Alpine component behind <x-ui.option-picker>: a select that shows an icon
 * next to each label, backed by a hidden input.
 */
export default function optionPicker({ options = [], value = null } = {}) {
    return {
        open: false,
        options,
        selected: value === null || value === '' ? null : String(value),

        current() {
            return this.options.find((option) => String(option.value) === String(this.selected)) ?? null;
        },

        isSelected(option) {
            return String(option.value) === String(this.selected);
        },

        choose(option) {
            this.selected = String(option.value);
            this.open = false;
        },
    };
}
