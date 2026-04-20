import './contact-table.scss';
import template from './contact-table.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Contact Table',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    title: { control: 'text' },
    rows: { control: 'object' },
  },
};

export const Default = {
  args: {
    title: 'Education and Sports Department - Mantralaya (Extension), Mumbai',
    headers: ['Name', 'Designation', 'Section/Desk', 'Address', 'Phone/email'],
    rows: [
      { name: 'Shri. Deepak Kesarkar', designation: 'Minister', section: 'School Education and Sports Department', address: 'Government of Maharashtra, Mantralaya (Extension)', contact: '(022) 22025301' },
      { name: 'Shri. Ranjit Singh Deol', designation: 'Principal Secretary', section: 'School Education and Sports Department', address: 'Government of Maharashtra, Mantralaya (Extension)', contact: '(022) 22026473' },
      { name: 'Smt. XYZ', designation: 'Deputy Secretary', section: 'School Education and Sports Department', address: 'Government of Maharashtra, Mantralaya (Extension)', contact: '(022) 12345678' }
    ],
  },
};
