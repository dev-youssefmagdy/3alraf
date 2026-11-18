import $ from 'jquery';
import { shrinkImage, trackCompression } from './image-compress';

/**
 * <x-image-dropzone>: drag-and-drop / click-to-pick images into a real file input, so the form submits as usual.
 * The picked files live here and are written back to input.files: a gallery keeps adding across picks (a bare input would
 * replace them), a single field swaps its one file. Delegated, so variant rows generated later work without any setup.
 */
const ROOT = '[data-dropzone]';
const TARGET = '[data-dz-target]';
const picked = new WeakMap(); // root element -> [{ source, file }]

const itemsOf = (root) => {
    if (!picked.has(root)) picked.set(root, []);
    return picked.get(root);
};
const inputOf = (root) => root.querySelector('input[type=file]');
const hasFiles = (e) => Array.from(e.originalEvent?.dataTransfer?.types || []).includes('Files');

function sync(root) {
    const dt = new DataTransfer();
    itemsOf(root).forEach((item) => dt.items.add(item.file));
    inputOf(root).files = dt.files;
    root.toggleAttribute('data-dirty', itemsOf(root).length > 0);
}

function clearError(root) {
    inputOf(root).classList.remove('is-invalid');
    $(root).next('.field-error').remove();
}

function showPreview(img, file) {
    const url = URL.createObjectURL(file);
    img.onload = img.onerror = () => URL.revokeObjectURL(url);
    img.src = url;
}

function render(root, item) {
    if (!inputOf(root).multiple) {
        showPreview(root.querySelector('[data-dz-img]'), item.source);
        root.setAttribute('data-filled', '');
        return;
    }
    const li = root.querySelector('[data-dz-item]').content.firstElementChild.cloneNode(true);
    showPreview(li.querySelector('[data-dz-img]'), item.source);
    li.dzItem = item;
    root.querySelector('[data-dz-list]').append(li);
}

function add(root, files) {
    const input = inputOf(root);
    let images = files.filter((f) => f.type.startsWith('image/'));
    if (images.length < files.length) window.toastr?.warning(root.dataset.notImage);
    clearError(root);

    if (input.multiple) {
        const known = itemsOf(root).map((i) => i.source);
        images = images.filter((f, n) => !known.concat(images.slice(0, n)).some((k) => k.name === f.name && k.size === f.size && k.lastModified === f.lastModified));
        const room = Number(root.dataset.max || Infinity) - itemsOf(root).length;
        if (images.length > room) {
            window.toastr?.warning(root.dataset.tooMany);
            images = images.slice(0, Math.max(room, 0));
        }
    } else if (images.length) {
        images = images.slice(0, 1);
        itemsOf(root).length = 0;
    }

    const fresh = images.map((file) => ({ source: file, file }));
    fresh.forEach((item) => { itemsOf(root).push(item); render(root, item); });
    sync(root); // also restores the kept files after the picker replaced them
    if (!fresh.length) return;

    // ajax-form waits for this before it builds the request
    trackCompression(Promise.all(fresh.map(async (item) => { item.file = await shrinkImage(item.source); })).then(() => sync(root)));
}

function remove(root, button) {
    if (inputOf(root).multiple) {
        const li = button.closest('[data-dz-new]');
        itemsOf(root).splice(itemsOf(root).indexOf(li.dzItem), 1);
        li.remove();
    } else {
        itemsOf(root).length = 0;
        const img = root.querySelector('[data-dz-img]');
        if (img.dataset.dzOriginal) img.src = img.dataset.dzOriginal;
        else { img.removeAttribute('src'); root.removeAttribute('data-filled'); }
    }
    sync(root);
    clearError(root);
    inputOf(root).focus({ preventScroll: true });
}

$(document).on('change', `${ROOT} input[type=file]`, function () {
    add(this.closest(ROOT), Array.from(this.files));
});

$(document).on('click', `${ROOT} [data-dz-remove]`, function () {
    remove(this.closest(ROOT), this);
});

$(document).on('dragenter dragover', `${ROOT} ${TARGET}`, function (e) {
    if (!hasFiles(e)) return;
    e.preventDefault();
    e.originalEvent.dataTransfer.dropEffect = 'copy';
    this.closest(ROOT).setAttribute('data-over', '');
});

$(document).on('dragleave', `${ROOT} ${TARGET}`, function (e) {
    if (!this.contains(e.relatedTarget)) this.closest(ROOT).removeAttribute('data-over');
});

$(document).on('drop', `${ROOT} ${TARGET}`, function (e) {
    if (!hasFiles(e)) return;
    e.preventDefault();
    const root = this.closest(ROOT);
    root.removeAttribute('data-over');
    add(root, Array.from(e.originalEvent.dataTransfer.files));
});

// A file dropped just beside a dropzone must not navigate away and lose the unsaved form.
$(document).on('dragover drop', (e) => {
    if (!hasFiles(e) || !document.querySelector(ROOT) || e.isDefaultPrevented()) return;
    e.preventDefault();
    if (e.type === 'dragover') e.originalEvent.dataTransfer.dropEffect = 'none';
});
