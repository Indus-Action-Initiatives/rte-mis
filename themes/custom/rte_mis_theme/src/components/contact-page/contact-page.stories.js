import './contact-page.scss';
import template from './contact-page.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Pages/Contact Page',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    page_title: { control: 'text' },
    table_title: { control: 'text' },
    table_headers: { control: 'object' },
    table_rows: { control: 'object' },
    cards_title: { control: 'text' },
    contact_cards: { control: 'object' },
  },
};

export const Default = {
  args: {
    page_title: 'Contact Us',
    table_title: 'Education and Sports Department - Mantralaya (Extension), Mumbai',
    table_headers: ['Name', 'Designation', 'Section/Desk', 'Address', 'Phone/email'],
    table_rows: [
      { name: 'Shri. Deepak Kesarkar', designation: 'Minister', section: 'School Education and Sports Department', address: 'Government of Maharashtra, Mantralaya (Extension)', contact: '(022) 22025301' },
      { name: 'Shri. Ranjit Singh Deol', designation: 'Principal Secretary', section: 'School Education and Sports Department', address: 'Government of Maharashtra, Mantralaya (Extension)', contact: '(022) 22026473' },
      { name: 'Smt. XYZ', designation: 'Secretary', section: 'School Education and Sports Department', address: 'Government of Maharashtra, Mantralaya (Extension)', contact: '(022) 12345678' }
    ],
    cards_title: 'Office Contact Details',
    contact_cards: [
      {
        department_name: 'School Education and Sports Department',
        address: 'Government of Maharashtra, Mantralaya (Extension), Room No. 432<br>Madam Cama Road, Hutatma Rajguru Chowk,<br>Mantralaya, Mumbai - 400032',
        phone: '(022) 22046261',
        email: 'acs.schedu@maharashtra.gov.in',
      },
      {
        department_name: 'Dy. Director of Education, Mumbai Division',
        address: 'Mittal Foundation Trust, Ayurvedic Hospital Building, 8th Floor, Netaji Subhash Road, Near Charni Road Station, Behind Kaivalyadham Yoga Centre, Marine Drive, Mumbai - 400002',
        phone: '-',
        email: 'dydemumbai@yahoo.com',
        jurisdiction: 'Mumbai (North, South, West), Thane, Raigad, Palghar',
      },
      {
        department_name: 'Maharashtra State Council of Examinations',
        address: '17, Dr. Ambedkar Road, Pune - 411001',
        phone: '(020) 26123066',
        email: 'mscepune@gmail.com',
      },
      {
        department_name: 'Directorate of Primary Education',
        address: 'Dr. Annie Besant Road, Central Building, Pune - 411001',
        phone: '(020) 26122485',
        email: 'dir.predu@maharashtra.gov.in',
      }
    ],
  },
};
