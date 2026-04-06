/* 
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */

function notify(message, type = 'success', timeout = 3000, url = false) {
    const container = document.getElementById('notify');

    const el = document.createElement('div');
    el.className = `alert alert-${type} mt-2`;
    el.innerText = message;

    container.appendChild(el);

    // автоскрытие
    setTimeout(() => {
          el.remove();
    }, timeout);
}