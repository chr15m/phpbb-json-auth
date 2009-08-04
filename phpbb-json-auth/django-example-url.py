from django.http import HttpResponse
from simplejson import dumps

def index(request):
	if request.user.is_authenticated() and request.user.get_profile():
		return HttpResponse(dumps({"username": request.user.username, "email": request.user.email, "admin": request.user.is_staff or request.user.is_superuser, "authenticated": request.user.is_authenticated()}))
	else:
		return HttpResponse(dumps({"authenticated": False}))

