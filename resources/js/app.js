import './bootstrap';
import Alpine from 'alpinejs';
import doseInput from './components/dose';
import patientPicker from './components/patientPicker';
import rowList from './components/rowList';
import typeahead from './components/typeahead';

// Alpine.js is the UI library for all client-side behaviour (spec §10.1).
Alpine.data('rowList', rowList);
Alpine.data('typeahead', typeahead);
Alpine.data('doseInput', doseInput);
Alpine.data('patientPicker', patientPicker);

window.Alpine = Alpine;
Alpine.start();
