import template from './input.twig';
import './input.scss';
import { renderTwig } from '../../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Atoms/Input',
  render: (args) => renderTwig(template, args),
};
export const Default = { args: { placeholder: 'Enter text...' } };