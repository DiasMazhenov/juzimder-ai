export function installFormInbox(projectId, api) {
  const actions = document.querySelector('.publication-actions');
  if (!actions) return;

  const open = document.createElement('button');
  open.type = 'button';
  open.className = 'secondary-link';
  open.textContent = 'Заявки';
  open.setAttribute('aria-haspopup', 'dialog');

  const dialog = document.createElement('dialog');
  dialog.id = 'form-inbox-dialog';
  dialog.setAttribute('aria-labelledby', 'form-inbox-title');
  dialog.innerHTML = '<div class="form-inbox-header"><div><p class="eyebrow">ОБРАЩЕНИЯ С САЙТА</p><h2 id="form-inbox-title">Заявки</h2></div><button type="button" aria-label="Закрыть">×</button></div><p class="form-inbox-status" role="status" aria-live="polite"></p><div class="form-inbox-list"></div>';
  document.body.append(dialog);
  const status = dialog.querySelector('.form-inbox-status');
  const list = dialog.querySelector('.form-inbox-list');

  const load = async () => {
    status.textContent = 'Загружаем заявки…';
    list.replaceChildren();
    try {
      const { submissions = [] } = await api(`/projects/${projectId}/form-submissions`);
      status.textContent = submissions.length ? `Последние ${submissions.length} заявок` : 'Пока нет заявок. Опубликованные формы появятся здесь после первой отправки.';
      for (const submission of submissions) {
        const article = document.createElement('article');
        article.className = 'form-inbox-item';
        const header = document.createElement('div');
        header.className = 'form-inbox-item-header';
        const title = document.createElement('strong');
        title.textContent = `${submission.pagePath || 'Страница'} · ${submission.createdAt || ''}`;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'form-inbox-delete';
        remove.textContent = 'Удалить';
        remove.addEventListener('click', async () => {
          if (!window.confirm('Удалить эту заявку без возможности восстановления?')) return;
          remove.disabled = true;
          try {
            await api(`/projects/${projectId}/form-submissions/${submission.id}/delete`, 'POST', {});
            await load();
          } catch (error) {
            remove.disabled = false;
            status.textContent = error.message || 'Не удалось удалить заявку.';
          }
        });
        header.append(title, remove);
        article.append(header);
        for (const [name, value] of Object.entries(submission.fields || {})) {
          const field = document.createElement('p');
          const label = document.createElement('span');
          label.textContent = `${name}: `;
          field.append(label, document.createTextNode(String(value)));
          article.append(field);
        }
        list.append(article);
      }
    } catch (error) {
      status.textContent = error.message || 'Не удалось загрузить заявки.';
    }
  };

  open.addEventListener('click', () => { dialog.showModal(); load(); });
  dialog.querySelector('[aria-label="Закрыть"]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
  actions.prepend(open);
}
