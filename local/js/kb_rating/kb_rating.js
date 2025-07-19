document.addEventListener('DOMContentLoaded', () => {
    const pathParts = location.pathname.replace(/^\/|\/$/g,'').split('/');
    const articleCode = pathParts.pop();
    if (!articleCode) return;

    fetch('/local/ajax/kb_rating.php?action=getStatus', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({code: articleCode})
    })
        .then(r => r.json())
        .then(res => {
            if (!res.enabled) return;                              // рейтинг выключен
            drawWidget(res.userRating, res.avg, res.count);
        });

    function drawWidget(myRating, avg, cnt){
        const wrapper = document.createElement('div');
        wrapper.id = 'kb-rating';
        wrapper.innerHTML = `
      <h3>Оцените статью</h3>
      <div class="stars">
        ${[1,2,3,4,5].map(i=>`<span data-v="${i}" class="star${myRating>=i?' selected':''}">&#9733;</span>`).join('')}
      </div>
      <div class="stat">Средняя: <span id="kb-avg">${avg}</span> (${cnt})</div>`;
        document.body.append(wrapper);

        document.querySelectorAll('#kb-rating .star').forEach(s=>{
            s.onclick = () => send(+s.dataset.v);
        });
    }
    function send(v){
        fetch('/local/ajax/kb_rating.php?action=saveRating', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({code: articleCode, rating: v})
        })
            .then(r=>r.json())
            .then(res=>{
                document.getElementById('kb-avg').textContent = res.avg;
                document.querySelectorAll('#kb-rating .star').forEach(s=>{
                    s.classList.toggle('selected', +s.dataset.v<=v);
                });
            });
    }
});
