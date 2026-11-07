import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import MImageUpload from './MImageUpload.vue';

/** jsdom has no real object URLs; the component only needs the pair to be called. */
beforeEach(() => {
  globalThis.URL.createObjectURL = vi.fn(() => 'blob:preview');
  globalThis.URL.revokeObjectURL = vi.fn();
});

/** `.at(-1)` needs es2022; this project's lib target is lower. */
function lastChange(wrapper: { emitted: (name: string) => unknown[][] | undefined }): unknown {
  const EMITTED = wrapper.emitted('onChange') ?? [];

  return (EMITTED[EMITTED.length - 1] ?? [])[0];
}

function pngFile(name = 'print.png', size = 1024): File {
  const file = new File(['x'], name, { type: 'image/png' });

  Object.defineProperty(file, 'size', { value: size });

  return file;
}

describe('MImageUpload', () => {
  it('hands the parent the file it was given', async () => {
    const wrapper = mountWithPlugins(MImageUpload);
    const FILE = pngFile();

    await wrapper.find('input[type="file"]').trigger('change');
    // The input is read-only in jsdom, so the drop path stands in for the picker.
    await wrapper.find('[data-testid="image-upload-dropzone"]').trigger('drop', {
      dataTransfer: { files: [FILE] },
    });

    expect(lastChange(wrapper)).toBe(FILE);
  });

  /*
   * The reason this component exists. The images people attach are screenshots, and a screenshot
   * starts in the clipboard — asking somebody to save it to disk first is three steps to undo what
   * the operating system already did.
   */
  it('takes an image pasted from the clipboard', async () => {
    const wrapper = mountWithPlugins(MImageUpload);
    const FILE = pngFile();

    window.dispatchEvent(
      Object.assign(new Event('paste'), {
        clipboardData: { items: [{ type: 'image/png', getAsFile: () => FILE }] },
        preventDefault: vi.fn(),
      })
    );
    await wrapper.vm.$nextTick();

    expect(lastChange(wrapper)).toBe(FILE);
  });

  /* Pasting text into another field of the same form must not be swallowed by the drop zone. */
  it('ignores a paste that carries no image', async () => {
    const wrapper = mountWithPlugins(MImageUpload);

    window.dispatchEvent(
      Object.assign(new Event('paste'), {
        clipboardData: { items: [{ type: 'text/plain', getAsFile: () => null }] },
        preventDefault: vi.fn(),
      })
    );
    await wrapper.vm.$nextTick();

    expect(wrapper.emitted('onChange')).toBeUndefined();
  });

  it('refuses a file that is too large, and says so', async () => {
    const wrapper = mountWithPlugins(MImageUpload, { props: { maxBytes: 100 } });

    await wrapper.find('[data-testid="image-upload-dropzone"]').trigger('drop', {
      dataTransfer: { files: [pngFile('grande.png', 5000)] },
    });

    expect(wrapper.emitted('onChange')).toBeUndefined();
    expect(wrapper.find('[data-testid="image-upload-error"]').exists()).toBe(true);
  });

  it('refuses a type outside the accept list', async () => {
    const wrapper = mountWithPlugins(MImageUpload);
    const GIF = new File(['x'], 'animado.gif', { type: 'image/gif' });

    await wrapper.find('[data-testid="image-upload-dropzone"]').trigger('drop', {
      dataTransfer: { files: [GIF] },
    });

    expect(wrapper.emitted('onChange')).toBeUndefined();
    expect(wrapper.find('[data-testid="image-upload-error"]').exists()).toBe(true);
  });

  it('shows an already-stored image so editing is not an empty box', () => {
    const wrapper = mountWithPlugins(MImageUpload, {
      props: { currentUrl: 'https://example.test/capa.png' },
    });

    expect(wrapper.find('[data-testid="image-upload-preview"]').attributes('src')).toBe(
      'https://example.test/capa.png'
    );
  });

  it('emits null when cleared, so the parent can tell "delete" from "unchanged"', async () => {
    const wrapper = mountWithPlugins(MImageUpload, {
      props: { currentUrl: 'https://example.test/capa.png' },
    });

    await wrapper.find('[data-testid="image-upload-clear"]').trigger('click');

    expect(lastChange(wrapper)).toBeNull();
  });

  /* An object URL pins the file in memory until it is revoked. */
  it('revokes the previous preview when the image is swapped', async () => {
    const wrapper = mountWithPlugins(MImageUpload);
    const zone = wrapper.find('[data-testid="image-upload-dropzone"]');

    await zone.trigger('drop', { dataTransfer: { files: [pngFile('a.png')] } });
    await zone.trigger('drop', { dataTransfer: { files: [pngFile('b.png')] } });

    expect(globalThis.URL.revokeObjectURL).toHaveBeenCalledWith('blob:preview');
  });

  it('stays silent while disabled', async () => {
    const wrapper = mountWithPlugins(MImageUpload, { props: { disabled: true } });

    await wrapper.find('[data-testid="image-upload-dropzone"]').trigger('drop', {
      dataTransfer: { files: [pngFile()] },
    });

    expect(wrapper.emitted('onChange')).toBeUndefined();
  });

  /*
   * `primary-50` does not flip with the scheme, and the drop zone's words are `text-muted`, which
   * does: in dark that was #888b8d on #faffe3, 3.34:1. The border alone marks the drag; the ground
   * stays the zone's own `bg-panel-muted`.
   */
  it('marks a drag with the border, keeping a ground its text reads on', async () => {
    const wrapper = mountWithPlugins(MImageUpload);
    const dropzone = wrapper.find('[data-testid="image-upload-dropzone"]');

    await dropzone.trigger('dragover');

    expect(dropzone.classes()).toContain('border-primary-500');

    expect(dropzone.classes().filter((name) => name.startsWith('bg-'))).toEqual(['bg-panel-muted']);
  });
});
