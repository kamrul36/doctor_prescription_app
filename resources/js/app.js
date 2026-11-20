import './bootstrap';
import Alpine from 'alpinejs';
import doseInput from './components/dose';
import patientPicker from './components/patientPicker';
import rowList from './components/rowList';
import templatePreview from './components/templatePreview';
import typeahead from './components/typeahead';
import validatedForm from './components/validatedForm';
import './toast';

// Alpine.js is the UI library for all client-side behaviour (spec §10.1).
Alpine.data('validatedForm', validatedForm);
Alpine.data('rowList', rowList);
Alpine.data('typeahead', typeahead);
Alpine.data('doseInput', doseInput);
Alpine.data('patientPicker', patientPicker);
Alpine.data('templatePreview', templatePreview);

window.Alpine = Alpine;
Alpine.start();
