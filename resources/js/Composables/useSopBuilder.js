import { ref, watch } from 'vue';
import axios from 'axios';

export function useSopBuilder(sop, initialPayload = null) {
    const rawBlocks = initialPayload?.blocks || (Array.isArray(initialPayload) ? initialPayload : []);
    const blocks = ref(JSON.parse(JSON.stringify(rawBlocks)));

    const status = ref('idle'); // 'idle' | 'saving' | 'saved' | 'error'
    const lastSaved = ref(null);
    const errorMessage = ref(null);
    const hasChanges = ref(false);

    let debounceTimer = null;

    // Helper: generate next unique block ID
    const generateBlockId = () => {
        const numbers = blocks.value
            .map(b => parseInt((b.id || '').replace(/\D/g, '')) || 0)
            .filter(n => n > 0);
        const max = numbers.length > 0 ? Math.max(...numbers) : 0;
        return `b${max + 1}`;
    };

    // Default props for each block type
    const defaultPropsForType = (type, blockId) => {
        const num = blockId.replace(/\D/g, '') || '1';
        switch (type) {
            case 'heading':
                return { text: '' };
            case 'text':
                return { html: '' };
            case 'checklist':
                return { items: [{ id: 'i1', text: '', required: true }] };
            case 'input':
                return {
                    key: `variable_${num}`,
                    label: `Campo de entrada ${num}`,
                    field: 'text',
                    required: true,
                    help: '',
                    filled_by: 'team',
                    options: [],
                };
            case 'media':
                return { kind: 'video', url: '', caption: '' };
            case 'decision':
                return {
                    question: '',
                    branches: [
                        { label: 'Sí', goto: '' },
                        { label: 'No', goto: '' },
                    ],
                };
            case 'ai_task':
                return {
                    skill_slug: '',
                    mode: 'internal',
                    model: 'openrouter/auto',
                    inputs: {},
                    output_key: `salida_${num}`,
                    requires_approval: true,
                    instructions_override: null,
                };
            case 'approval':
                return { role: 'editor', instructions: '' };
            case 'handoff':
                return { to: 'client', message: '', notify: true };
            default:
                return {};
        }
    };

    // Save draft via AJAX
    const saveDraft = async () => {
        if (status.value === 'saving') return;

        status.value = 'saving';
        errorMessage.value = null;

        try {
            const payload = {
                blocks: {
                    schema_version: 1,
                    blocks: blocks.value,
                },
            };

            const response = await axios.put(route('sops.draft.save', sop.id), payload);

            if (response.data?.success) {
                status.value = 'saved';
                lastSaved.value = new Date();
                hasChanges.value = false;
            }
        } catch (err) {
            status.value = 'error';
            errorMessage.value = err.response?.data?.message || 'Error al autoguardar el borrador.';
        }
    };

    // Trigger debounced autosave (2.5s)
    const triggerDebouncedSave = () => {
        hasChanges.value = true;
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            saveDraft();
        }, 2500);
    };

    // Watch for deep changes in blocks
    watch(
        blocks,
        () => {
            triggerDebouncedSave();
        },
        { deep: true }
    );

    // Actions
    const addBlock = (type) => {
        const id = generateBlockId();
        const newBlock = {
            id,
            type,
            props: defaultPropsForType(type, id),
        };
        blocks.value.push(newBlock);
    };

    const removeBlock = (index) => {
        blocks.value.splice(index, 1);
    };

    const updateBlock = (index, updated) => {
        blocks.value[index] = updated;
    };

    // Publish current draft
    const publish = async (changelog = '') => {
        if (debounceTimer) clearTimeout(debounceTimer);

        const payload = {
            blocks: {
                schema_version: 1,
                blocks: blocks.value,
            },
            changelog,
        };

        const response = await axios.post(route('sops.publish', sop.id), payload);
        return response.data;
    };

    return {
        blocks,
        status,
        lastSaved,
        errorMessage,
        hasChanges,
        addBlock,
        removeBlock,
        updateBlock,
        saveDraft,
        publish,
    };
}
