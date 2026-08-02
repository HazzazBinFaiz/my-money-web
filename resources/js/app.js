import Alpine from 'alpinejs';
import imagePicker from './image-picker';

window.Alpine = Alpine;

Alpine.data('imagePicker', imagePicker);

Alpine.start();
