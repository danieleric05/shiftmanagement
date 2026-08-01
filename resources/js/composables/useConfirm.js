import { reactive } from 'vue';

const state = reactive({
    show: false,
    title: 'Confirmation',
    message: '',
    danger: false,
    resolve: null,
});

export function useConfirm() {
    const confirmer = (message, { title = 'Confirmation', danger = false } = {}) => {
        state.show = true;
        state.title = title;
        state.message = message;
        state.danger = danger;

        return new Promise((resolve) => {
            state.resolve = resolve;
        });
    };

    const repondre = (value) => {
        state.show = false;
        state.resolve?.(value);
        state.resolve = null;
    };

    return { confirmer, repondre, state };
}
