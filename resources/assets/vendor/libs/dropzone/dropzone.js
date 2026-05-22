import Dropzone from 'dropzone/dist/dropzone';

Dropzone.autoDiscover = false;

try {
  window.Dropzone = Dropzone;
} catch (e) {
}

export { Dropzone };
