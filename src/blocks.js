import { tokenValue } from './design-tokens.js';

const palette = Object.freeze({
  ink: tokenValue('ink'),
  forest: tokenValue('forest'),
  canvas: tokenValue('canvas'),
  paper: tokenValue('paper'),
  body: tokenValue('body'),
  muted: tokenValue('muted'),
  line: tokenValue('line'),
  accent: tokenValue('accent'),
  font: tokenValue('fontFamily'),
  radius: tokenValue('radius')
});

const text = (tagName, content, style = {}) => ({ type: 'text', tagName, content, style });
const link = (content, href, style = {}) => ({ type: 'link', content, attributes: { href }, style });

export const blocks = [
  {
    id: 'hero',
    label: 'Hero',
    description: 'Первый экран · Flex',
    content: () => ({
      tagName: 'section',
      name: 'Hero',
      style: {
        display: 'flex', 'flex-direction': 'column', gap: '24px', padding: '80px 8%',
        'min-height': '480px', 'background-color': palette.canvas, color: palette.ink,
        'font-family': palette.font
      },
      components: [
        text('p', 'ВАША ИДЕЯ. ВАШЕ ПРОСТРАНСТВО.', { 'font-size': '11px', 'letter-spacing': '2px', color: palette.body }),
        text('h1', 'Место для\nнового начала.', { 'font-size': 'clamp(36px, 6vw, 76px)', 'line-height': '1.05', 'letter-spacing': '-3px', margin: '0', 'max-width': '700px' }),
        text('p', 'Расскажите о том, что вы создаёте. Добавьте свой текст, выберите цвета и сделайте эту страницу своей.', { 'max-width': '440px', 'line-height': '1.7', color: palette.body }),
        link('Начать знакомство', '#contact', { padding: '16px 24px', 'background-color': palette.forest, color: '#fff', 'border-radius': palette.radius, 'text-decoration': 'none', 'align-self': 'flex-start' })
      ]
    })
  },
  {
    id: 'features',
    label: 'Возможности',
    description: 'Три карточки · Grid',
    content: () => ({
      tagName: 'section',
      name: 'Возможности',
      attributes: { id: 'about' },
      style: { padding: '56px 8%', display: 'grid', 'grid-template-columns': 'repeat(auto-fit, minmax(min(100%, 180px), 1fr))', gap: '24px', 'font-family': palette.font },
      components: ['Продумано до деталей', 'Создано для людей', 'Готово к переменам'].map((title, i) => ({
        tagName: 'article',
        style: { padding: '24px', 'border-top': '1px solid ' + palette.line },
        components: [
          text('p', '0' + (i + 1), { color: palette.body, 'font-size': '12px' }),
          text('h3', title, { 'font-size': '20px', color: palette.ink }),
          text('p', 'Добавьте описание вашей услуги или преимущества.', { 'line-height': '1.6', color: palette.muted })
        ]
      }))
    })
  },
  {
    id: 'cta',
    label: 'Призыв к действию',
    description: 'Контактная секция · Flex',
    content: () => ({
      tagName: 'section',
      name: 'Контакты',
      attributes: { id: 'contact' },
      style: { display: 'flex', 'flex-direction': 'column', gap: '16px', padding: '56px 8%', 'background-color': palette.ink, color: palette.paper, 'font-family': palette.font },
      components: [
        text('h2', 'Давайте создадим что-то вместе.', { 'font-size': '36px', margin: '0' }),
        text('p', 'Оставьте здесь свой email или ссылку для связи.'),
        link('Написать нам', 'mailto:hello@example.com', { color: palette.accent })
      ]
    })
  },
  {
    id: 'text',
    label: 'Текст',
    description: 'Редактируемый абзац',
    content: () => text('p', 'Нажмите дважды, чтобы изменить этот текст.', { padding: '24px', 'font-family': palette.font, 'font-size': '18px', color: palette.ink })
  },
  {
    id: 'image',
    label: 'Изображение',
    description: 'Укажите HTTPS-адрес',
    content: () => ({ type: 'image', style: { width: '100%', 'max-width': '640px', 'min-height': '160px' } })
  },
  {
    id: 'navigation',
    label: 'Навигация',
    description: 'Логотип и ссылки · Flex',
    content: () => ({
      tagName: 'header',
      name: 'Навигация',
      style: { display: 'flex', 'align-items': 'center', 'justify-content': 'space-between', 'flex-wrap': 'wrap', gap: '18px', padding: '20px 8%', 'background-color': palette.paper, color: palette.ink, 'font-family': palette.font },
      components: [
        text('span', 'Название проекта', { 'font-size': '18px', 'font-weight': '700', color: palette.ink }),
        {
          tagName: 'nav',
          name: 'Ссылки',
          attributes: { 'aria-label': 'Основная навигация' },
          style: { display: 'flex', 'align-items': 'center', 'flex-wrap': 'wrap', gap: '18px' },
          components: [
            link('О проекте', '#about', { color: palette.body, 'text-decoration': 'none' }),
            link('Услуги', '#services', { color: palette.body, 'text-decoration': 'none' }),
            link('Контакты', '#contact', { color: palette.body, 'text-decoration': 'none' })
          ]
        },
        link('Обсудить проект', '#contact', { padding: '11px 16px', 'background-color': palette.forest, color: '#fff', 'border-radius': palette.radius, 'text-decoration': 'none' })
      ]
    })
  },
  {
    id: 'process',
    label: 'Этапы работы',
    description: 'Три шага · Grid',
    content: () => ({
      tagName: 'section',
      name: 'Этапы работы',
      attributes: { id: 'services' },
      style: { padding: '64px 8%', 'background-color': palette.canvas, color: palette.ink, 'font-family': palette.font },
      components: [
        text('p', 'ПОНЯТНО ОТ ИДЕИ ДО ЗАПУСКА', { 'font-size': '11px', 'letter-spacing': '1.8px', color: palette.body }),
        text('h2', 'Как устроена работа', { 'font-size': 'clamp(28px, 4vw, 42px)', 'line-height': '1.15', 'max-width': '620px' }),
        {
          tagName: 'div',
          name: 'Шаги',
          style: { display: 'grid', 'grid-template-columns': 'repeat(auto-fit, minmax(min(100%, 180px), 1fr))', gap: '16px' },
          components: [
            ['01', 'Знакомство', 'Расскажите о задаче и о том, для кого создаётся сайт.'],
            ['02', 'Структура', 'Соберите содержание страницы из готовых секций.'],
            ['03', 'Запуск', 'Проверьте адаптивность и подготовьте страницу к публикации.']
          ].map(([number, title, description]) => ({
            tagName: 'article',
            style: { padding: '22px', 'background-color': palette.paper, 'border-radius': palette.radius },
            components: [
              text('p', number, { color: palette.forest, 'font-size': '12px', 'letter-spacing': '1px' }),
              text('h3', title, { 'font-size': '18px', color: palette.ink }),
              text('p', description, { 'font-size': '14px', 'line-height': '1.65', color: palette.muted })
            ]
          }))
        }
      ]
    })
  },
  {
    id: 'testimonial',
    label: 'Отзыв',
    description: 'Цитата клиента · Flex',
    content: () => ({
      tagName: 'section',
      name: 'Отзыв',
      style: { display: 'flex', 'flex-direction': 'column', gap: '18px', padding: '64px 8%', 'background-color': palette.paper, color: palette.ink, 'font-family': palette.font },
      components: [
        text('p', 'ОТЗЫВ КЛИЕНТА', { 'font-size': '11px', 'letter-spacing': '1.8px', color: palette.body }),
        text('p', '«Добавьте сюда короткий отзыв о совместной работе и её результате».', { 'max-width': '760px', 'font-size': 'clamp(22px, 3.5vw, 34px)', 'line-height': '1.35', color: palette.ink }),
        text('strong', 'Имя клиента', { 'font-size': '14px' }),
        text('p', 'Роль или название компании', { 'font-size': '12px', color: palette.muted })
      ]
    })
  },
  {
    id: 'faq',
    label: 'Вопросы и ответы',
    description: 'Ответы посетителям · Stack',
    content: () => ({
      tagName: 'section',
      name: 'Вопросы и ответы',
      style: { padding: '64px 8%', color: palette.ink, 'font-family': palette.font },
      components: [
        text('p', 'ВОПРОСЫ И ОТВЕТЫ', { 'font-size': '11px', 'letter-spacing': '1.8px', color: palette.body }),
        text('h2', 'Что важно знать', { 'font-size': 'clamp(28px, 4vw, 42px)' }),
        {
          tagName: 'div',
          name: 'Список ответов',
          style: { display: 'flex', 'flex-direction': 'column', 'max-width': '820px' },
          components: [
            ['С чего начать?', 'Опишите задачу, выберите секции и замените примерный текст своим содержанием.'],
            ['Можно ли менять секции?', 'Да. Каждый блок можно редактировать на холсте и настраивать в инспекторе.'],
            ['Как проверить мобильную версию?', 'Переключите устройство в редакторе и проверьте страницу на узком экране.']
          ].map(([question, answer]) => ({
            tagName: 'article',
            style: { padding: '18px 0', 'border-bottom': '1px solid ' + palette.line },
            components: [
              text('h3', question, { 'font-size': '16px', 'font-weight': '600', color: palette.ink }),
              text('p', answer, { 'font-size': '14px', 'line-height': '1.65', color: palette.muted })
            ]
          }))
        }
      ]
    })
  },
  {
    id: 'footer',
    label: 'Подвал',
    description: 'Навигация и подпись · Flex',
    content: () => ({
      tagName: 'footer',
      name: 'Подвал',
      style: { display: 'flex', 'justify-content': 'space-between', 'align-items': 'center', 'flex-wrap': 'wrap', gap: '16px', padding: '28px 8%', 'background-color': palette.ink, color: palette.paper, 'font-family': palette.font },
      components: [
        text('p', 'Название проекта · 2026', { 'font-size': '12px', color: palette.paper }),
        {
          tagName: 'nav',
          name: 'Ссылки подвала',
          attributes: { 'aria-label': 'Дополнительная навигация' },
          style: { display: 'flex', 'flex-wrap': 'wrap', gap: '16px' },
          components: [
            link('О проекте', '#about', { 'font-size': '12px', color: palette.accent, 'text-decoration': 'none' }),
            link('Связаться', '#contact', { 'font-size': '12px', color: palette.accent, 'text-decoration': 'none' })
          ]
        }
      ]
    })
  },
  {
    id: 'container',
    label: 'Контейнер',
    description: 'Область для вложенных элементов · Flex',
    content: () => ({
      tagName: 'div',
      name: 'Контейнер',
      style: { display: 'flex', 'flex-direction': 'column', gap: '12px', 'min-height': '120px', padding: '20px', 'border': '1px dashed ' + palette.line, 'border-radius': palette.radius },
      components: []
    })
  },
  {
    id: 'button',
    label: 'Кнопка',
    description: 'Редактируемая кнопка-ссылка',
    content: () => ({
      tagName: 'button',
      name: 'Кнопка',
      attributes: { type: 'button' },
      content: 'Нажмите здесь',
      style: { padding: '12px 18px', 'background-color': palette.forest, color: '#fff', 'border': '0', 'border-radius': palette.radius, 'font-family': palette.font, 'font-size': '14px', cursor: 'pointer' }
    })
  }
];

export function registerBlocks(editor) {
  for (const block of blocks) editor.Blocks.add(block.id, { label: block.label, content: block.content() });
}
