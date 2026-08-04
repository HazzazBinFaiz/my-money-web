import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const CROP_SIZE = 69;

/**
 * Alpine component behind <x-image-picker>.
 *
 * Grid mode lists the images the user may reuse; editor mode uploads a new
 * file, lets the user scale/drag it inside a fixed 69x69 circular frame and
 * uploads the cropped PNG.
 */
export default function imagePicker({ type, imageId = null, imageUrl = null }) {
    return {
        type,
        selectedId: imageId,
        selectedUrl: imageUrl,

        open: false,
        mode: 'grid',
        loading: false,
        saving: false,
        error: null,
        images: [],
        cropper: null,
        hasFile: false,

        openModal() {
            this.open = true;
            this.error = null;
            this.showGrid();
        },

        closeModal() {
            this.open = false;
            this.destroyCropper();
        },

        showGrid() {
            this.destroyCropper();
            this.mode = 'grid';
            this.load();
        },

        showEditor() {
            this.mode = 'editor';
            this.hasFile = false;
            this.error = null;
        },

        async load() {
            this.loading = true;

            try {
                const response = await fetch(`/media?type=${this.type}`, {
                    headers: { Accept: 'application/json' },
                });
                const payload = await response.json();
                this.images = payload.data ?? [];
            } catch (e) {
                this.error = 'Could not load images.';
            } finally {
                this.loading = false;
            }
        },

        choose(image) {
            this.selectedId = image.id;
            this.selectedUrl = image.url;
            this.closeModal();
        },

        clear() {
            this.selectedId = null;
            this.selectedUrl = null;
        },

        pickFile(event) {
            const file = event.target.files[0];

            if (!file) {
                return;
            }

            this.destroyCropper();
            this.hasFile = true;

            const image = this.$refs.editorImage;
            image.src = URL.createObjectURL(file);

            this.$nextTick(() => {
                this.cropper = new Cropper(image, {
                    aspectRatio: 1,
                    viewMode: 0,
                    dragMode: 'move',
                    autoCropArea: 1,
                    background: false,
                    movable: true,
                    zoomable: true,
                    cropBoxMovable: false,
                    cropBoxResizable: false,
                    toggleDragModeOnDblclick: false,
                    guides: false,
                    center: false,
                    highlight: false,
                });
            });
        },

        zoom(step) {
            this.cropper?.zoom(step);
        },

        destroyCropper() {
            this.cropper?.destroy();
            this.cropper = null;
            this.hasFile = false;

            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = '';
            }
        },

        /**
         * Crops to a 69x69 circle and uploads it as PNG.
         */
        async done() {
            if (!this.cropper || this.saving) {
                return;
            }

            this.saving = true;
            this.error = null;

            try {
                const source = this.cropper.getCroppedCanvas({
                    width: CROP_SIZE,
                    height: CROP_SIZE,
                    imageSmoothingQuality: 'high',
                });

                const blob = await this.toCircularPng(source);
                const form = new FormData();
                form.append('image', blob, 'icon.png');
                form.append('type', this.type);

                const response = await fetch('/media', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: form,
                });

                if (!response.ok) {
                    throw new Error('upload failed');
                }

                const payload = await response.json();
                this.images.unshift(payload.data);
                this.choose(payload.data);
            } catch (e) {
                this.error = 'Upload failed. Try again.';
            } finally {
                this.saving = false;
            }
        },

        toCircularPng(source) {
            const canvas = document.createElement('canvas');
            canvas.width = CROP_SIZE;
            canvas.height = CROP_SIZE;

            const context = canvas.getContext('2d');
            context.beginPath();
            context.arc(CROP_SIZE / 2, CROP_SIZE / 2, CROP_SIZE / 2, 0, Math.PI * 2);
            context.closePath();
            context.clip();
            context.drawImage(source, 0, 0, CROP_SIZE, CROP_SIZE);

            return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        },
    };
}
